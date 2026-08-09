<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationStatusLog;
use App\Models\District;
use App\Models\FormField;
use App\Models\Scheme;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    private const APPLICANT_KEYS = [
        'full_name', 'father_name', 'cnic', 'phone', 'district_id',
        'tehsil', 'union_council', 'village', 'address', 'land_location',
        'email', 'gender',
    ];

    public function __construct(private OtpService $otp) {}

    public function create(string $slug): View|RedirectResponse
    {
        $scheme = Scheme::query()
            ->with(['department', 'publishedForm.fields', 'publishedForm.documents', 'districts'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $scheme->isOpenForApplications()) {
            return redirect()
                ->route('schemes.show', $scheme->slug)
                ->with('error', 'This scheme is not currently open for applications.');
        }

        $form = $scheme->publishedForm;
        $districts = $scheme->scope === 'district' && $scheme->districts->isNotEmpty()
            ? $scheme->districts->sortBy('name')->values()
            : District::where('is_active', true)->orderBy('name')->get();

        $phoneVerified = session('phone_verified');

        return view('public.schemes.apply', compact('scheme', 'form', 'districts', 'phoneVerified'));
    }

    public function store(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $scheme = Scheme::query()
            ->with(['publishedForm.fields', 'publishedForm.documents'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        if (! $scheme->isOpenForApplications()) {
            throw ValidationException::withMessages([
                'scheme' => 'This scheme is not currently open for applications.',
            ]);
        }

        $form = $scheme->publishedForm;
        if (! $form) {
            throw ValidationException::withMessages([
                'form' => 'No published application form is available for this scheme.',
            ]);
        }

        $request->merge([
            'cnic' => preg_replace('/\D+/', '', (string) $request->input('cnic')),
            'phone' => preg_replace('/\D+/', '', (string) $request->input('phone')),
        ]);

        $request->validate([
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'phone' => ['required', 'string', 'min:11', 'max:15'],
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['required', 'string', 'max:150'],
            'district_id' => ['required', 'exists:districts,id'],
            'tehsil' => ['required', 'string', 'max:150'],
            'land_location' => ['required', 'string', 'max:500'],
        ], [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
        ]);

        $cnic = (string) $request->input('cnic');
        $phone = (string) $request->input('phone');

        if (! $this->otp->isVerified($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please verify your phone number with OTP before submitting.',
            ]);
        }

        $rules = $this->buildValidationRules($form->fields, $form->documents);
        $request->validate($rules, [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
            'cnic.size' => 'CNIC must be exactly 13 digits.',
        ]);

        $existingApplicant = Applicant::where('cnic', $cnic)->first();
        if ($existingApplicant && Application::where('scheme_id', $scheme->id)
            ->where('applicant_id', $existingApplicant->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'cnic' => 'An application with this CNIC already exists for this scheme.',
            ]);
        }

        $application = DB::transaction(function () use ($request, $scheme, $form, $cnic, $phone) {
            $applicantData = [
                'cnic' => $cnic,
                'full_name' => $request->input('full_name'),
                'father_name' => $request->input('father_name'),
                'phone' => $phone,
                'phone_verified' => true,
                'district_id' => (int) $request->input('district_id'),
                'tehsil' => $request->input('tehsil'),
                'union_council' => $request->input('union_council'),
                'village' => $request->input('village'),
                'address' => $request->input('address'),
                'land_location' => $request->input('land_location'),
                'email' => $request->input('email'),
                'gender' => $request->input('gender'),
            ];

            $applicant = Applicant::updateOrCreate(
                ['cnic' => $cnic],
                collect($applicantData)->reject(fn ($v) => $v === null || $v === '')->all()
            );

            $application = Application::create([
                'application_no' => Application::generateApplicationNo(),
                'scheme_id' => $scheme->id,
                'applicant_id' => $applicant->id,
                'district_id' => (int) $request->input('district_id'),
                'form_id' => $form->id,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => null,
                'to_status' => 'pending',
                'note' => 'Application submitted by citizen.',
            ]);

            foreach ($form->fields as $field) {
                if ($field->is_core && in_array($field->field_key, self::APPLICANT_KEYS, true)) {
                    continue;
                }

                $value = $request->input($field->field_key);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }

                ApplicationFieldValue::create([
                    'application_id' => $application->id,
                    'form_field_id' => $field->id,
                    'value' => $value !== null ? (string) $value : null,
                ]);
            }

            foreach ($form->documents as $document) {
                $fileKey = 'documents.'.$document->id;
                if (! $request->hasFile($fileKey)) {
                    continue;
                }

                $file = $request->file($fileKey);
                $path = $file->store('applications/'.$application->application_no, 'public');

                ApplicationDocument::create([
                    'application_id' => $application->id,
                    'form_document_id' => $document->id,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize() ?: 0,
                    'uploaded_at' => now(),
                ]);
            }

            return $application;
        });

        session()->forget('phone_verified');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => route('applications.success', $application->application_no),
                'application_no' => $application->application_no,
            ]);
        }

        return redirect()
            ->route('applications.success', $application->application_no)
            ->with('success', 'Your application has been submitted successfully.');
    }

    public function sendOtp(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:11', 'max:15'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        $code = $this->otp->send($phone);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully.',
                'dev_otp' => session('dev_otp'),
            ]);
        }

        return back()->with('success', 'OTP sent to your phone.')->with('dev_otp', $code);
    }

    public function verifyOtp(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:11', 'max:15'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $phone = preg_replace('/\D+/', '', $data['phone']);
        $ok = $this->otp->verify($phone, $data['otp']);

        if (! $ok) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.'], 422);
            }

            throw ValidationException::withMessages(['otp' => 'Invalid or expired OTP.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Phone verified successfully.', 'phone' => $phone]);
        }

        return back()->with('success', 'Phone number verified successfully.');
    }

    public function success(string $application_no): View
    {
        $application = Application::query()
            ->with(['scheme.department', 'applicant', 'district'])
            ->where('application_no', $application_no)
            ->firstOrFail();

        return view('public.applications.success', compact('application'));
    }

    public function track(Request $request): View
    {
        $application = null;

        if ($request->isMethod('post') || ($request->filled('application_no') && $request->filled('cnic'))) {
            $request->merge([
                'cnic' => preg_replace('/\D+/', '', (string) $request->input('cnic')),
            ]);

            $data = $request->validate([
                'application_no' => ['required', 'string', 'max:50'],
                'cnic' => ['required', 'regex:/^\d{13}$/'],
            ], [
                'cnic.regex' => 'CNIC must be exactly 13 digits without dashes.',
            ]);

            $cnic = $data['cnic'];

            $application = Application::query()
                ->with(['scheme.department', 'applicant', 'district', 'statusLogs'])
                ->where('application_no', trim($data['application_no']))
                ->whereHas('applicant', fn ($q) => $q->where('cnic', $cnic))
                ->first();

            if (! $application) {
                return view('public.applications.track', [
                    'application' => null,
                    'notFound' => true,
                ]);
            }
        }

        return view('public.applications.track', [
            'application' => $application,
            'notFound' => false,
        ]);
    }

    private function buildValidationRules($fields, $documents): array
    {
        $rules = [
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'phone' => ['required', 'string', 'min:11', 'max:15'],
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['required', 'string', 'max:150'],
            'district_id' => ['required', 'exists:districts,id'],
            'tehsil' => ['required', 'string', 'max:150'],
            'land_location' => ['required', 'string', 'max:500'],
        ];

        /** @var FormField $field */
        foreach ($fields as $field) {
            if (in_array($field->field_key, ['cnic', 'phone', 'full_name', 'father_name', 'district_id', 'tehsil', 'land_location'], true)) {
                continue;
            }

            $fieldRules = [];
            $fieldRules[] = $field->is_mandatory ? 'required' : 'nullable';

            switch ($field->field_type) {
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'checkbox':
                    $fieldRules = [$field->is_mandatory ? 'required' : 'nullable', 'array'];
                    $rules[$field->field_key.'.*'] = ['string'];
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:2000';
            }

            $rules[$field->field_key] = $fieldRules;
        }

        foreach ($documents as $document) {
            $ext = implode(',', $document->allowedExtensionsArray());
            $maxKb = max(1, (int) $document->max_size_mb) * 1024;
            $docRules = [
                $document->is_mandatory ? 'required' : 'nullable',
                'file',
                'max:'.$maxKb,
            ];
            if ($ext !== '') {
                $docRules[] = 'mimes:'.$ext;
            }
            $rules['documents.'.$document->id] = $docRules;
        }

        return $rules;
    }
}

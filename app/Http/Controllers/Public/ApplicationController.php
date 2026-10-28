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
use App\Models\Tehsil;
use App\Models\UnionCouncil;
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
            ? $scheme->districts
            : District::where('is_active', true)->get();

        if (class_exists(\Collator::class)) {
            $collator = new \Collator(app()->getLocale() === 'ur' ? 'ur_PK' : 'en_US');
            $districts = $districts->sort(function ($a, $b) use ($collator) {
                return $collator->compare((string) $a->t('name'), (string) $b->t('name'));
            })->values();
        } else {
            $districts = $districts->sortBy(fn ($d) => $d->t('name'), SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

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

        $selectedDistrictId = $request->input('district_id');
        $selectedTehsil = $request->input('tehsil');
        $tehsilModel = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('tehsils')) {
            if (is_numeric($selectedTehsil)) {
                $tehsilModel = Tehsil::where('id', $selectedTehsil)
                    ->when($selectedDistrictId, fn ($q) => $q->where('district_id', $selectedDistrictId))
                    ->first();
            } elseif ($selectedTehsil) {
                $tehsilModel = Tehsil::where('name', $selectedTehsil)
                    ->when($selectedDistrictId, fn ($q) => $q->where('district_id', $selectedDistrictId))
                    ->first()
                    ?? Tehsil::where('name', $selectedTehsil)->first();
            }
        }
        $tehsilName = $tehsilModel?->name ?? (string) $selectedTehsil;

        $hasUnionCouncils = ($tehsilModel && \Illuminate\Support\Facades\Schema::hasTable('union_councils'))
            ? UnionCouncil::where('tehsil_id', $tehsilModel->id)->exists()
            : false;

        $selectedUc = $request->input('union_council');
        $ucModel = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('union_councils')) {
            if (is_numeric($selectedUc)) {
                $ucModel = UnionCouncil::find($selectedUc);
            } elseif ($selectedUc) {
                $ucModel = UnionCouncil::where('name', $selectedUc)
                    ->when($tehsilModel, fn ($q) => $q->where('tehsil_id', $tehsilModel->id))
                    ->first();
            }
        }
        $unionCouncilName = $ucModel?->name ?? ($selectedUc ?: null);

        $request->validate([
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'phone' => ['required', 'string', 'min:11', 'max:15'],
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['required', 'string', 'max:150'],
            'district_id' => ['required', 'exists:districts,id'],
            'tehsil' => ['required', 'string', 'max:150'],
            'union_council' => $hasUnionCouncils ? ['required', 'string', 'max:150'] : ['nullable', 'string', 'max:150'],
            'village' => ['nullable', 'string', 'max:150'],
            'land_location' => ['required', 'string', 'max:500'],
        ], [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
            'union_council.required' => 'Please select a Union Council.',
        ]);

        $cnic = (string) $request->input('cnic');
        $phone = (string) $request->input('phone');

        $rules = $this->buildValidationRules($form->fields, $form->documents, $hasUnionCouncils);
        $customMessages = [
            'cnic.regex' => 'CNIC must be exactly 13 digits.',
            'cnic.size' => 'CNIC must be exactly 13 digits.',
            'union_council.required' => 'Please select a Union Council.',
        ];
        foreach ($form->fields as $field) {
            if ($field->field_type === 'checkbox_with_text') {
                $inputVal = $request->input($field->field_key);
                $hasChecked = false;
                if (is_array($inputVal)) {
                    foreach ($inputVal as $idx => $item) {
                        if (is_array($item) && !empty($item['checked'])) {
                            $hasChecked = true;
                            $text = trim((string)($item['text'] ?? ''));
                            $optLabel = trim((string)($item['label'] ?? ''));
                            if ($text === '') {
                                $rules[$field->field_key.'.'.$idx.'.text'] = ['required', 'string', 'max:2000'];
                                $customMessages[$field->field_key.'.'.$idx.'.text.required'] = 'Please provide details for ' . ($optLabel ?: $field->field_label) . '.';
                            }
                        }
                    }
                    if (isset($inputVal['checked']) && !empty($inputVal['checked'])) {
                        $hasChecked = true;
                        $text = trim((string)($inputVal['text'] ?? ''));
                        if ($text === '') {
                            $rules[$field->field_key.'.text'] = ['required', 'string', 'max:2000'];
                            $customMessages[$field->field_key.'.text.required'] = 'Please provide details for ' . $field->field_label . '.';
                        }
                    }
                }

                if ($field->is_mandatory && !$hasChecked) {
                    $rules[$field->field_key] = ['required', function ($attribute, $value, $fail) use ($field) {
                        $fail('Please select at least one option for ' . $field->field_label . '.');
                    }];
                }
            }
        }

        // 50:50 Cost-Sharing Scheme Formula: 1 bag (50kg) per 4 kanals of available land
        $hasBagsField = $form->fields->contains(fn ($f) => in_array($f->field_key, ['bags_required', 'seed_bags_required'], true));
        $isWheatCostSharing = $scheme->id === \App\Services\WheatSeedMisService::SCHEME_ID
            || $scheme->slug === 'distribution-of-wheat-seed-cost-sharing'
            || str_contains(strtolower($scheme->title), 'wheat');

        if (($hasBagsField || $isWheatCostSharing) && ($request->has('bags_required') || $request->has('seed_bags_required'))) {
            $bagsKey = $request->has('bags_required') ? 'bags_required' : 'seed_bags_required';
            $landVal = $request->input('land_kanals');
            $kanalsPerBag = (float) (\App\Models\SiteSetting::getValue('wheat_seed_kanals_per_bag') ?: 4);

            $rules[$bagsKey] = [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($landVal, $kanalsPerBag) {
                    if ($landVal === null || $landVal === '' || !is_numeric($landVal) || (float)$landVal <= 0) {
                        $fail(__('portal.enter_land_first'));
                        return;
                    }

                    $land = (float) $landVal;
                    $maxBags = (int) floor($land / $kanalsPerBag);

                    if ((float) $value > $maxBags) {
                        $fail(__('portal.bags_limit_exceeded', [
                            'bags' => $value,
                            'max' => $maxBags,
                            'kanals' => $land,
                            'rate' => $kanalsPerBag,
                        ]));
                    }
                },
            ];
            $customMessages[$bagsKey.'.required'] = 'The number of 50kg seed bags required is mandatory.';
            $customMessages[$bagsKey.'.numeric'] = 'The number of bags must be a valid number.';
            $customMessages[$bagsKey.'.min'] = 'The number of bags required must be at least 1.';
        }

        $request->validate($rules, $customMessages);

        // Enforce CNIC verification via external API
        $verifiedCnic = (string) session('verified_cnic');
        if ($verifiedCnic !== $cnic) {
            $surveyCfg = config('services.survey_external');
            $hasApi = ! empty($surveyCfg['base_url']);

            if ($hasApi) {
                $resp = \App\Services\SurveyExternalApi::make()->lookup(\App\Support\Cnic::dashed($cnic));
                if (! ($resp['success'] ?? false)) {
                    throw ValidationException::withMessages([
                        'cnic' => __('portal.cnic_not_found'),
                    ]);
                }
                session(['verified_cnic' => $cnic]);
            }
        }

        $existingApplicant = Applicant::where('cnic', $cnic)->first();
        if ($existingApplicant && Application::where('scheme_id', $scheme->id)
            ->where('applicant_id', $existingApplicant->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'cnic' => 'An application with this CNIC already exists for this scheme.',
            ]);
        }

        $application = DB::transaction(function () use ($request, $scheme, $form, $cnic, $phone, $tehsilName, $unionCouncilName) {
            $applicantData = [
                'cnic' => $cnic,
                'full_name' => $request->input('full_name'),
                'father_name' => $request->input('father_name'),
                'phone' => $phone,
                'phone_verified' => true,
                'district_id' => (int) $request->input('district_id'),
                'tehsil' => $tehsilName,
                'union_council' => $unionCouncilName,
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
                if ($field->field_type === 'checkbox_with_text') {
                    if (is_array($value)) {
                        $selectedParts = [];
                        foreach ($value as $item) {
                            if (is_array($item)) {
                                $isChecked = !empty($item['checked']) && ((string)$item['checked'] === '1' || $item['checked'] === true || $item['checked'] === 'yes');
                                if ($isChecked) {
                                    $label = trim((string)($item['label'] ?? ''));
                                    $text = trim((string)($item['text'] ?? ''));
                                    if ($label !== '' && $text !== '') {
                                        $selectedParts[] = $label . ': ' . $text;
                                    } elseif ($label !== '') {
                                        $selectedParts[] = $label;
                                    } elseif ($text !== '') {
                                        $selectedParts[] = 'Yes: ' . $text;
                                    } else {
                                        $selectedParts[] = 'Yes';
                                    }
                                }
                            }
                        }

                        if (empty($selectedParts) && isset($value['checked'])) {
                            $isChecked = !empty($value['checked']) && ((string)$value['checked'] === '1' || $value['checked'] === true || $value['checked'] === 'yes');
                            $text = trim((string)($value['text'] ?? ''));
                            if ($isChecked) {
                                $selectedParts[] = $text !== '' ? ('Yes: ' . $text) : 'Yes';
                            }
                        }

                        $value = !empty($selectedParts) ? implode('; ', $selectedParts) : null;
                    } elseif (is_string($value)) {
                        $value = trim($value) !== '' ? trim($value) : null;
                    } else {
                        $value = null;
                    }
                } elseif (is_array($value)) {
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

            // Execute full lifecycle initialization: hierarchy resolution, DD assignment or unassigned queue, immutable audit logs
            app(\App\Services\ApplicationLifecycleService::class)->onSubmission($application);

            return $application;
        });

        session()->forget('phone_verified');
        session()->forget('verified_cnic');

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
                ->with(['scheme.department', 'applicant', 'district', 'statusLogs', 'bills'])
                ->where('application_no', trim($data['application_no']))
                ->whereHas('applicant', fn ($q) => $q->where('cnic', $cnic))
                ->first();

            if (! $application) {
                return view('public.applications.track', [
                    'application' => null,
                    'notFound' => true,
                ]);
            }

            if ($application->current_stage === Application::STAGE_CHALLAN_ISSUED && ! $application->psid) {
                app(\App\Services\GbpayService::class)->createOrUpdateBillForApplication($application);
                $application->load('bills');
            }
        }

        return view('public.applications.track', [
            'application' => $application,
            'notFound' => false,
        ]);
    }

    public function downloadChallan(string $application_no)
    {
        $application = Application::with(['scheme.department', 'scheme.challanTemplate', 'applicant', 'district'])
            ->where('application_no', $application_no)
            ->firstOrFail();

        $breakdown = $application->getChallanBreakdown();
        $template = $breakdown['template'];
        $validityDays = (int) ($template->validity_days ?: 10);

        if (! $application->challan_no) {
            $application->challan_no = 'CHL-'.now()->format('Y').'-'.str_pad((string) $application->id, 5, '0', STR_PAD_LEFT);
            $application->challan_amount = (float) $breakdown['final_bill'];
            $application->challan_issued_at = $application->challan_issued_at ?: now();
            $application->challan_due_date = $application->challan_due_date ?: now()->addDays($validityDays)->toDateString();
            $application->saveQuietly();
        }

        // Ensure GBPay Bill & 20-digit PSID exist for this application challan
        $bill = app(\App\Services\GbpayService::class)->createOrUpdateBillForApplication($application);
        $psidConfig = \App\Models\PsidConfig::getActive();
        $qrCodeBase64 = \App\Support\QrCodeGenerator::toBase64Png($bill->psid, 3, 2);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('public.applications.challan-pdf', [
            'application' => $application,
            'bill' => $bill,
            'psidConfig' => $psidConfig,
            'qrCodeBase64' => $qrCodeBase64,
            'scheme' => $application->scheme,
            'applicant' => $application->applicant,
            'district' => $application->district,
            'template' => $template,
            'breakdown' => $breakdown,
        ])->setPaper('a4', 'landscape')
          ->setOption([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled' => false,
              'dpi' => 96,
              'defaultFont' => 'DejaVu Sans',
          ]);

        return $pdf->download("Challan-{$application->challan_no}.pdf");
    }

    public function uploadPaymentProof(Request $request, string $application_no): RedirectResponse
    {
        $application = Application::where('application_no', $application_no)->firstOrFail();

        $request->validate([
            'payment_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $request->file('payment_receipt');
        $path = $file->store('applications/'.$application->application_no.'/receipts', 'public');

        $systemUser = \App\Models\User::first() ?? new \App\Models\User(['id' => 1, 'name' => 'Citizen Portal']);
        app(\App\Services\ApplicationLifecycleService::class)->transition(
            $application,
            Application::STAGE_PAYMENT_PROOF_SUBMITTED,
            $systemUser,
            [
                'payment_receipt_path' => $path,
                'note' => 'Citizen uploaded bank payment deposit slip/proof.',
            ]
        );

        return back()->with('success', 'Payment proof submitted successfully! Awaiting verification by Accounts Officer.');
    }

    public function submitCorrection(Request $request, string $application_no): RedirectResponse
    {
        $application = Application::where('application_no', $application_no)->firstOrFail();

        $data = $request->validate([
            'correction_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        app(\App\Services\ApplicationLifecycleService::class)->submitCorrection($application, $data);

        return back()->with('success', 'Correction submitted successfully. Application returned to Deputy Director review.');
    }

    private function buildValidationRules($fields, $documents, bool $hasUnionCouncils = false): array
    {
        $rules = [
            'cnic' => ['required', 'regex:/^\d{13}$/'],
            'phone' => ['required', 'string', 'min:11', 'max:15'],
            'full_name' => ['required', 'string', 'max:150'],
            'father_name' => ['required', 'string', 'max:150'],
            'district_id' => ['required', 'exists:districts,id'],
            'tehsil' => ['required', 'string', 'max:150'],
            'union_council' => $hasUnionCouncils ? ['required', 'string', 'max:150'] : ['nullable', 'string', 'max:150'],
            'village' => ['nullable', 'string', 'max:150'],
            'land_location' => ['required', 'string', 'max:500'],
        ];

        /** @var FormField $field */
        foreach ($fields as $field) {
            if (in_array($field->field_key, ['cnic', 'phone', 'full_name', 'father_name', 'district_id', 'tehsil', 'union_council', 'village', 'land_location'], true)) {
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
                case 'checkbox_with_text':
                    $fieldRules = [$field->is_mandatory ? 'required' : 'nullable', 'array'];
                    $rules[$field->field_key.'.*.checked'] = ['nullable', 'in:1'];
                    $rules[$field->field_key.'.*.label'] = ['nullable', 'string'];
                    $rules[$field->field_key.'.*.text'] = ['nullable', 'string', 'max:2000'];
                    $rules[$field->field_key.'.checked'] = ['nullable'];
                    $rules[$field->field_key.'.text'] = ['nullable', 'string', 'max:2000'];
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

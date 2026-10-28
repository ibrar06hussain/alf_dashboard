<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationAssignment;
use App\Models\ApplicationStatusLog;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationReviewController extends Controller
{
    public function __construct(
        private QuotaService $quotas,
        private \App\Services\ApplicationLifecycleService $lifecycle
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = $this->filteredQuery($request);

        $departments = Department::when(! $user->isGlobalScope() && ! $user->hasRole('Division Director'), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDepartmentIds());
        })->orderBy('display_order')->get();

        $districts = District::when(! $user->isGlobalScope(), function ($q) use ($user) {
            $q->whereIn('id', $user->authorizedDistrictIds());
        })->orderBy('name')->get();

        $schemes = Scheme::when(! $user->isGlobalScope() && ! $user->hasRole('Division Director'), function ($q) use ($user) {
            $q->whereIn('department_id', $user->authorizedDepartmentIds());
        })->orderBy('title')->get(['id', 'title', 'department_id']);

        $statuses = Application::STATUSES;

        $pageStats = [
            ['label' => 'Matching', 'value' => (clone $query)->count(), 'icon' => 'fa-solid fa-folder-open', 'tone' => 'green'],
            ['label' => 'Under Review', 'value' => (clone $query)->whereIn('current_stage', ['submitted', 'under_review'])->count(), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
            ['label' => 'Field Survey', 'value' => (clone $query)->whereIn('current_stage', ['field_survey_assigned', 'visit_scheduled', 'visit_completed', 'findings_submitted'])->count(), 'icon' => 'fa-solid fa-magnifying-glass-location', 'tone' => 'teal'],
            ['label' => 'Approved / Challan', 'value' => (clone $query)->whereIn('current_stage', ['approved', 'challan_issued', 'payment_proof_submitted', 'payment_verified', 'eligible_beneficiary'])->count(), 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'blue'],
            ['label' => 'Disbursed / Done', 'value' => (clone $query)->whereIn('current_stage', ['partially_distributed', 'fully_distributed', 'completed', 'disbursed'])->count(), 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'purple'],
            ['label' => 'Unassigned Queue', 'value' => (clone $query)->where('is_unassigned', true)->count(), 'icon' => 'fa-solid fa-triangle-exclamation', 'tone' => 'rose'],
        ];

        $applications = $query->paginate(25)->withQueryString();

        return view('admin.applications.index', compact(
            'applications', 'departments', 'districts', 'schemes', 'statuses', 'pageStats'
        ));
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user();
        $format = strtolower($request->string('format', 'xlsx')->toString());
        $filename = 'alf-applications-'.now()->format('Ymd_His');
        $masked = false;

        $generatedAt = now()->format('d M Y, h:i A');
        $generatedBy = $user->name . ' (' . ($user->getRoleNames()->first() ?? 'Officer') . ')';

        $query = $this->filteredQuery($request);

        if ($format === 'pdf') {
            ini_set('memory_limit', '512M');
            set_time_limit(180);

            $totalCount = (clone $query)->count();
            $requestedLimit = (int) $request->input('per_page', 500);
            $limit = ($requestedLimit > 0 && $requestedLimit <= 500) ? $requestedLimit : 500;

            $apps = $query->limit($limit)->get();

            AccessAuditLog::log('beneficiaries_exported', Application::class, null, [
                'count' => $apps->count(),
                'format' => $format,
                'is_masked' => false,
            ]);

            $rowsData = $apps->map(function ($app) {
                $applicant = $app->applicant;
                $cnic = \App\Support\Cnic::dashed($applicant?->cnic);
                $phone = $applicant?->phone ?: '—';
                $land = $app->getAvailableLandKanals();
                $bags = $app->getBagsRequired();
                $statusClass = match($app->status) {
                    'directorate_approved' => 'badge-approved',
                    'district_verified' => 'badge-verified',
                    'disbursed' => 'badge-disbursed',
                    'rejected' => 'badge-rejected',
                    default => 'badge-pending',
                };
                $tehsil = $applicant?->tehsil;
                $village = $applicant?->village;
                $location = trim(($tehsil ? $tehsil : '') . ($tehsil && $village ? ', ' : '') . ($village ? $village : ''));

                return [
                    'application_no' => $app->application_no,
                    'full_name' => $applicant?->full_name ?? '—',
                    'father_name' => $applicant?->father_name ?? '',
                    'cnic' => $cnic,
                    'phone' => $phone,
                    'district' => $app->district?->name ?? '—',
                    'location' => $location ?: '—',
                    'scheme' => $app->scheme?->title ?? '—',
                    'department' => $app->scheme?->department?->name ?? '',
                    'land' => $land !== null ? number_format($land, 1) : null,
                    'bags' => $bags,
                    'status_label' => $app->statusLabel(),
                    'status_class' => $statusClass,
                    'submitted_at' => optional($app->submitted_at ?? $app->created_at)?->format('d M Y') ?? '—',
                ];
            });

            $filters = [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'department_id' => $request->integer('department_id'),
                'district_id' => $request->integer('district_id'),
                'scheme_id' => $request->integer('scheme_id'),
                'min_land' => $request->input('min_land'),
                'max_land' => $request->input('max_land'),
                'min_bags' => $request->input('min_bags'),
                'max_bags' => $request->input('max_bags'),
            ];

            $pdf = Pdf::loadView('admin.applications.export-pdf', [
                'rowsData' => $rowsData,
                'totalCount' => $totalCount,
                'limit' => $limit,
                'masked' => $masked,
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
                'filters' => $filters,
            ])->setPaper('a4', 'landscape')
              ->setOption([
                  'isHtml5ParserEnabled' => true,
                  'isRemoteEnabled' => false,
                  'dpi' => 96,
                  'defaultFont' => 'DejaVu Sans',
              ]);

            return $pdf->download($filename.'.pdf');
        }

        $rows = $query->limit(5000)->get();

        AccessAuditLog::log('beneficiaries_exported', Application::class, null, [
            'count' => $rows->count(),
            'format' => $format,
            'is_masked' => false,
        ]);

        if ($format === 'csv') {
            return Excel::download(
                new ApplicationsExport($rows, $masked, $generatedAt, $generatedBy),
                $filename.'.csv',
                \Maatwebsite\Excel\Excel::CSV
            );
        }

        return Excel::download(
            new ApplicationsExport($rows, $masked, $generatedAt, $generatedBy),
            $filename.'.xlsx'
        );
    }

    private function filteredQuery(Request $request)
    {
        $user = auth()->user();
        $query = Application::with([
            'scheme.department',
            'applicant',
            'district',
            'assignments.assignee',
            'fieldValues.formField',
        ])
            ->authorizedFor($user)
            ->latest();

        $authorizedDistricts = $user->authorizedDistrictIds();
        $authorizedDepts = $user->authorizedDepartmentIds();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('department_id')) {
            $deptId = $request->integer('department_id');
            if ($user->isGlobalScope() || $user->hasRole('Division Director') || in_array($deptId, $authorizedDepts, true)) {
                $query->whereHas('scheme', fn ($q) => $q->where('department_id', $deptId));
            }
        }

        if ($request->filled('district_id')) {
            $distId = $request->integer('district_id');
            if ($user->isGlobalScope() || in_array($distId, $authorizedDistricts, true)) {
                $query->where('district_id', $distId);
            }
        }

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }

        // Available Land Filter (Min / Max Kanals)
        $minLand = $request->input('min_land') ?? $request->input('land_kanals');
        if ($minLand !== null && $minLand !== '' && is_numeric($minLand)) {
            $val = (float) $minLand;
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$val]);
            });
        }

        if ($request->filled('max_land') && is_numeric($request->input('max_land'))) {
            $val = (float) $request->input('max_land');
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$val]);
            });
        }

        // Bags Required Filter (Min / Max Bags)
        $minBags = $request->input('min_bags') ?? $request->input('bags_required');
        if ($minBags !== null && $minBags !== '' && is_numeric($minBags)) {
            $val = (int) $minBags;
            $query->where(function ($bq) use ($val) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) >= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($val) {
                    $minLandForBags = max(0, ($val - 1) * 4 + 0.01);
                    $wheatQ->whereHas('scheme', fn ($sq) => $sq->where('slug', 'like', '%wheat%'))
                           ->whereHas('fieldValues', function ($fq) use ($minLandForBags) {
                               $fq->where(function ($sub) {
                                   $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                       ->orWhere('form_field_id', 8);
                               })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$minLandForBags]);
                           });
                });
            });
        }

        if ($request->filled('max_bags') && is_numeric($request->input('max_bags'))) {
            $val = (int) $request->input('max_bags');
            $query->where(function ($bq) use ($val) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) <= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($val) {
                    $maxLandForBags = $val * 4;
                    $wheatQ->whereHas('scheme', fn ($sq) => $sq->where('slug', 'like', '%wheat%'))
                           ->whereHas('fieldValues', function ($fq) use ($maxLandForBags) {
                               $fq->where(function ($sub) {
                                   $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                       ->orWhere('form_field_id', 8);
                               })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$maxLandForBags]);
                           });
                });
            });
        }

        if ($request->filled('stage')) {
            $query->where('current_stage', $request->string('stage'));
        }

        if ($request->filled('unassigned') && $request->boolean('unassigned')) {
            $query->where('is_unassigned', true);
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('application_no', 'like', "%{$q}%")
                    ->orWhereHas('applicant', function ($aq) use ($q) {
                        $aq->where('full_name', 'like', "%{$q}%")
                            ->orWhere('cnic', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('tehsil', 'like', "%{$q}%")
                            ->orWhere('union_council', 'like', "%{$q}%")
                            ->orWhere('village', 'like', "%{$q}%");
                    });
            });
        }

        return $query;
    }

    public function show(Application $application): View
    {
        $user = auth()->user();

        if (! $user->can('view', $application)) {
            abort(403, 'Unauthorized access to application in another administrative scope.');
        }

        $application->load([
            'scheme.department',
            'applicant.district',
            'district',
            'division',
            'assignedUser',
            'responsibleOffice',
            'form',
            'fieldValues.formField',
            'documents.formDocument',
            'statusLogs.user',
            'assignments.assignee',
            'assignments.assigner',
            'surveys.surveyor',
            'surveys.assignedBy',
            'paymentVerifiedBy',
            'reactivatedBy',
            'bills',
        ]);

        $allowedTransitions = $this->allowedTransitions($user, $application);
        $quotaStatus = $this->quotas->status($application->scheme, $application->district_id);
        $slaStatus = $application->slaStatus();

        $fieldOfficers = $user->can('assignFieldOfficer', $application)
            ? User::whereHas('roles', fn ($rq) => $rq->where('name', 'Field Officer'))
                ->where(function ($q) use ($application) {
                    $q->where('district_id', $application->district_id)
                        ->orWhereHas('officeAssignments', function ($aq) use ($application) {
                            $aq->active()->where('district_id', $application->district_id);
                        });
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        $deputyDirectors = $user->hasRole('Super Admin') || $user->hasRole('Secretary') || $user->hasRole('Division Director')
            ? User::whereHas('roles', fn ($rq) => $rq->whereIn('name', ['District Deputy Director', 'District Officer']))
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        AccessAuditLog::log('application_viewed', Application::class, (string) $application->id);

        return view('admin.applications.show', compact(
            'application', 'allowedTransitions', 'quotaStatus', 'slaStatus', 'fieldOfficers', 'deputyDirectors'
        ));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('view', $application)) {
            abort(403, 'Unauthorized access.');
        }

        $data = $request->validate([
            'status' => ['required', 'string'],
            'approved_quantity' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $targetStatus = $data['status'];

        // Legacy mapping if needed
        $allowed = $this->allowedTransitions($user, $application);
        if (! in_array($targetStatus, $allowed, true)) {
            return back()->with('error', 'You are not authorized to transition this application to the requested status.');
        }

        if (in_array($targetStatus, ['directorate_approved', 'approved', 'disbursed', 'fully_distributed'], true)
            && ! $this->quotas->canApprove($application->scheme, $application->district_id)
            && ! in_array($application->status, ['directorate_approved', 'approved', 'disbursed', 'fully_distributed'], true)) {
            $quota = $this->quotas->status($application->scheme, $application->district_id);

            return back()->with('error', sprintf(
                'District quota reached (%d / %d). Cannot approve further applications for this district.',
                $quota['used'] ?? 0,
                $quota['quota'] ?? 0
            ));
        }

        try {
            $expectedLock = $request->filled('lock_version') ? (int) $request->input('lock_version') : null;
            $this->lifecycle->transition($application, $targetStatus, $user, [
                'note' => $data['note'] ?? $data['verification_note'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'verification_note' => $data['verification_note'] ?? null,
                'approved_quantity' => $data['approved_quantity'] ?? null,
            ], $expectedLock);

            return back()->with('success', 'Application transitioned to ' . Application::translateStatus($targetStatus) . '.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignSurvey(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('assignSurvey', $application)) {
            abort(403, 'Unauthorized to delegate field survey for this application.');
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
        ]);

        $fieldOfficer = User::findOrFail($data['assigned_to']);

        try {
            $this->lifecycle->assignSurvey($application, $user, $fieldOfficer, $data);
            return back()->with('success', "Field survey successfully assigned to Field Officer {$fieldOfficer->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function scheduleSurveyVisit(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'scheduled_visit_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->scheduleVisit($survey, $user, Carbon::parse($data['scheduled_visit_at']), $data['notes'] ?? null);
            return back()->with('success', 'Field survey visit scheduled successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function completeSurveyVisit(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->completeVisit($survey, $user, $data['notes'] ?? null);
            return back()->with('success', 'Field survey visit marked completed.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function submitSurveyFindings(Request $request, \App\Models\ApplicationSurvey $survey): RedirectResponse
    {
        $user = auth()->user();

        // 1. Process and validate GPS points (3 or more required from mobile/GPS)
        $rawGps = $request->input('gps_points');
        if (is_string($rawGps)) {
            $rawGps = json_decode($rawGps, true) ?: [];
        } elseif (! is_array($rawGps)) {
            $rawGps = [];
        }

        $validPoints = [];
        foreach ($rawGps as $pt) {
            if (isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng'])) {
                $validPoints[] = [
                    'point_number' => count($validPoints) + 1,
                    'lat' => round((float) $pt['lat'], 7),
                    'lng' => round((float) $pt['lng'], 7),
                    'accuracy' => isset($pt['accuracy']) && is_numeric($pt['accuracy']) ? round((float) $pt['accuracy'], 2) : null,
                    'label' => ! empty($pt['label']) ? (string) $pt['label'] : 'Point ' . (count($validPoints) + 1),
                    'timestamp' => $pt['timestamp'] ?? now()->toIso8601String(),
                ];
            }
        }

        if (count($validPoints) < 3) {
            return back()->withInput()->withErrors([
                'gps_points' => 'A minimum of 3 GPS boundary/perimeter points of the land must be captured from the mobile device/GPS.',
            ]);
        }

        // 2. Validate request fields and land photos (max 3 photos, max 5MB each)
        $data = $request->validate([
            'land_verified_kanals' => ['nullable', 'numeric', 'min:0'],
            'water_source_verified' => ['nullable', 'string', 'max:255'],
            'road_access_verified' => ['nullable', 'string', 'max:255'],
            'findings_summary' => ['required', 'string', 'max:2000'],
            'recommendation' => ['required', 'in:recommended,not_recommended,needs_further_audit'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5 MB each
            'photo_1' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_2' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_3' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        // 3. Process uploaded photos
        $uploadedPhotos = [];
        $photoFiles = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $idx => $file) {
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => 'Land Photo ' . (count($photoFiles) + 1)];
                }
            }
        }

        foreach (['photo_1' => 'General Land View', 'photo_2' => 'Irrigation / Water Source', 'photo_3' => 'Road Access / Parcel Corner'] as $key => $defaultLabel) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => $defaultLabel];
                }
            }
        }

        $photoFiles = array_slice($photoFiles, 0, 3);

        foreach ($photoFiles as $item) {
            $file = $item['file'];
            $path = $file->store('surveys/photos/' . $survey->application_id, 'public');
            $uploadedPhotos[] = [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'label' => $item['label'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $data['gps_points'] = $validPoints;
        $data['photos'] = $uploadedPhotos;

        try {
            $this->lifecycle->submitFindings($survey, $user, $data);
            return back()->with('success', 'Field survey recorded: ' . count($validPoints) . ' GPS boundary points and ' . count($uploadedPhotos) . ' land photos submitted to Deputy Director.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function requestCorrection(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('requestCorrection', $application)) {
            abort(403, 'Unauthorized to request correction on this application.');
        }

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_CORRECTION_REQUIRED, $user, [
                'remarks' => $data['remarks'],
            ]);
            return back()->with('success', 'Correction requested from applicant. Application status updated.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function verifyPayment(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('verifyPayment', $application)) {
            abort(403, 'Only authorized Accounts Officers can verify payments.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_PAYMENT_VERIFIED, $user, [
                'note' => $data['note'] ?? 'Challan payment deposit verified by Accounts Officer.',
            ]);

            // Advance to eligible beneficiary
            $this->lifecycle->transition($application, Application::STAGE_ELIGIBLE_BENEFICIARY, $user, [
                'note' => 'Beneficiary confirmed eligible following complete payment verification.',
            ]);

            return back()->with('success', 'Payment verified and beneficiary cleared for benefit allocation.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function allocateStock(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('allocateStock', $application)) {
            abort(403, 'Unauthorized to allocate stock for this application.');
        }

        $data = $request->validate([
            'allocated_quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycle->transition($application, Application::STAGE_STOCK_ALLOCATED, $user, $data);
            return back()->with('success', "Stock allocated successfully ({$data['allocated_quantity']} units).");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reactivateDormant(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('reactivate', $application)) {
            abort(403, 'Unauthorized to reactivate dormant applications. DD authority required.');
        }

        $data = $request->validate([
            'documented_reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->lifecycle->reactivateDormant($application, $user, $data['documented_reason']);
            return back()->with('success', 'Dormant application reactivated with fresh 14-day challan issued.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function assignDeputyDirector(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasAnyRole(['Super Admin', 'Secretary', 'Division Director'])) {
            abort(403, 'Unauthorized to assign Deputy Director.');
        }

        $data = $request->validate([
            'deputy_director_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $dd = User::findOrFail($data['deputy_director_id']);

        DB::transaction(function () use ($application, $dd, $user, $data) {
            $application->update([
                'assigned_to' => $dd->id,
                'assigned_at' => now(),
                'is_unassigned' => false,
                'current_stage' => Application::STAGE_UNDER_REVIEW,
                'status' => 'pending',
                'stage_updated_at' => now(),
                'lock_version' => $application->lock_version + 1,
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => Application::STAGE_SUBMITTED,
                'to_status' => Application::STAGE_UNDER_REVIEW,
                'user_id' => $user->id,
                'note' => "Assigned from unassigned queue to Deputy Director {$dd->name}. Note: " . ($data['note'] ?? 'N/A'),
            ]);

            AccessAuditLog::log('application_dd_manually_assigned', Application::class, (string) $application->id, [
                'assigned_by' => $user->name,
                'assigned_to' => $dd->name,
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('success', "Application successfully assigned to Deputy Director {$dd->name}.");
    }

    public function assignFieldOfficer(Request $request, Application $application): RedirectResponse
    {
        return $this->assignSurvey($request, $application);
    }

    public function submitTaskReport(Request $request, ApplicationAssignment $assignment): RedirectResponse
    {
        $user = auth()->user();

        if ($assignment->assigned_to !== $user->id && ! $user->hasRole('Super Admin')) {
            abort(403, 'You are not assigned to this task.');
        }

        $rawGps = $request->input('gps_points');
        if (is_string($rawGps)) {
            $rawGps = json_decode($rawGps, true) ?: [];
        } elseif (! is_array($rawGps)) {
            $rawGps = [];
        }

        $validPoints = [];
        foreach ($rawGps as $pt) {
            if (isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng'])) {
                $validPoints[] = [
                    'point_number' => count($validPoints) + 1,
                    'lat' => round((float) $pt['lat'], 7),
                    'lng' => round((float) $pt['lng'], 7),
                    'accuracy' => isset($pt['accuracy']) && is_numeric($pt['accuracy']) ? round((float) $pt['accuracy'], 2) : null,
                    'label' => ! empty($pt['label']) ? (string) $pt['label'] : 'Point ' . (count($validPoints) + 1),
                    'timestamp' => $pt['timestamp'] ?? now()->toIso8601String(),
                ];
            }
        }

        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'land_verified_kanals' => ['nullable', 'numeric'],
            'recommendation' => ['nullable', 'in:recommended,not_recommended,needs_further_audit'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_1' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_2' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'photo_3' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $uploadedPhotos = [];
        $photoFiles = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => 'Land Photo ' . (count($photoFiles) + 1)];
                }
            }
        }

        foreach (['photo_1' => 'General Land View', 'photo_2' => 'Irrigation / Water Source', 'photo_3' => 'Road Access / Parcel Corner'] as $key => $defaultLabel) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file && $file->isValid()) {
                    $photoFiles[] = ['file' => $file, 'label' => $defaultLabel];
                }
            }
        }

        $photoFiles = array_slice($photoFiles, 0, 3);

        foreach ($photoFiles as $item) {
            $file = $item['file'];
            $path = $file->store('surveys/photos/' . $assignment->application_id, 'public');
            $uploadedPhotos[] = [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'size_kb' => round($file->getSize() / 1024, 1),
                'label' => $item['label'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        $assignment->status = $data['status'];
        $assignment->notes = $data['notes'] ?? $assignment->notes;
        $assignment->report_data = [
            'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'gps_points' => $validPoints,
            'photos' => $uploadedPhotos,
            'submitted_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];

        if ($data['status'] === 'completed') {
            $assignment->completed_at = now();
        }

        $assignment->save();

        // Also sync with active ApplicationSurvey if linked
        $survey = \App\Models\ApplicationSurvey::where('application_id', $assignment->application_id)
            ->where('assigned_to', $user->id)
            ->where('status', '!=', \App\Models\ApplicationSurvey::STATUS_FINDINGS_SUBMITTED)
            ->first();

        if ($survey && $data['status'] === 'completed') {
            $this->lifecycle->submitFindings($survey, $user, [
                'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
                'recommendation' => $data['recommendation'] ?? \App\Models\ApplicationSurvey::REC_RECOMMENDED,
                'findings_summary' => $data['notes'] ?? 'Task verification completed.',
                'gps_points' => $validPoints,
                'photos' => $uploadedPhotos,
            ]);
        }

        AccessAuditLog::log('task_report_submitted', ApplicationAssignment::class, (string) $assignment->id, [
            'status' => $data['status'],
            'application_id' => $assignment->application_id,
            'gps_points_count' => count($validPoints),
            'photos_count' => count($uploadedPhotos),
        ]);

        return back()->with('success', 'Task report recorded successfully.');
    }

    private function allowedTransitions($user, Application $application): array
    {
        // Technical Super Admin, Secretary, and Division Directors have ZERO default approval authority
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Division Director']) &&
            ! $user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            return [];
        }

        $allowed = [];
        $currentStage = $application->current_stage ?: $application->status;

        // Deputy Director permissions
        if ($user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            if (in_array($currentStage, ['pending', Application::STAGE_SUBMITTED], true)) {
                $allowed[] = Application::STAGE_UNDER_REVIEW;
                $allowed[] = Application::STAGE_FIELD_SURVEY_ASSIGNED;
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                // legacy
                $allowed[] = 'district_verified';
                $allowed[] = 'directorate_approved';
                $allowed[] = 'rejected';
            } elseif ($currentStage === Application::STAGE_UNDER_REVIEW) {
                $allowed[] = Application::STAGE_FIELD_SURVEY_ASSIGNED;
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                $allowed[] = 'directorate_approved';
            } elseif ($currentStage === Application::STAGE_FINDINGS_SUBMITTED) {
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
                $allowed[] = 'directorate_approved';
            } elseif ($currentStage === 'district_verified') {
                $allowed[] = Application::STAGE_APPROVED;
                $allowed[] = Application::STAGE_REJECTED;
                $allowed[] = 'directorate_approved';
            } elseif ($currentStage === Application::STAGE_DORMANT) {
                $allowed[] = Application::STAGE_UNDER_REVIEW;
                $allowed[] = Application::STAGE_CHALLAN_ISSUED;
            } elseif (in_array($currentStage, [Application::STAGE_APPROVED, 'directorate_approved'], true)) {
                $allowed[] = Application::STAGE_CHALLAN_ISSUED;
                $allowed[] = Application::STAGE_ELIGIBLE_BENEFICIARY;
            }
        }

        // Accounts Officer
        if ($user->can('payments.verify') || $user->hasRole('Accounts Officer')) {
            if ($currentStage === Application::STAGE_PAYMENT_PROOF_SUBMITTED) {
                $allowed[] = Application::STAGE_PAYMENT_VERIFIED;
                $allowed[] = Application::STAGE_CORRECTION_REQUIRED;
            }
        }

        // Distribution Officer
        if ($user->can('distribution.confirm_delivery') || $user->hasRole('Distribution Officer')) {
            if ($currentStage === Application::STAGE_ELIGIBLE_BENEFICIARY) {
                $allowed[] = Application::STAGE_STOCK_ALLOCATED;
            } elseif ($currentStage === Application::STAGE_STOCK_ALLOCATED) {
                $allowed[] = Application::STAGE_PARTIALLY_DISTRIBUTED;
                $allowed[] = Application::STAGE_FULLY_DISTRIBUTED;
                $allowed[] = 'disbursed';
            } elseif ($currentStage === Application::STAGE_PARTIALLY_DISTRIBUTED) {
                $allowed[] = Application::STAGE_FULLY_DISTRIBUTED;
                $allowed[] = 'disbursed';
            }
        }

        return array_values(array_unique($allowed));
    }
}

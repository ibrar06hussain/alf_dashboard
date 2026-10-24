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
    public function __construct(private QuotaService $quotas) {}

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
            ['label' => 'Pending', 'value' => (clone $query)->where('status', 'pending')->count(), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
            ['label' => 'Approved', 'value' => (clone $query)->where('status', 'directorate_approved')->count(), 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'blue'],
            ['label' => 'Disbursed', 'value' => (clone $query)->where('status', 'disbursed')->count(), 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'teal'],
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
        $rows = $this->filteredQuery($request)->limit(5000)->get();
        $filename = 'alf-applications-'.now()->format('Ymd_His');

        $masked = false;

        AccessAuditLog::log('beneficiaries_exported', Application::class, null, [
            'count' => $rows->count(),
            'format' => $format,
            'is_masked' => false,
        ]);

        $generatedAt = now()->format('d M Y, h:i A');
        $generatedBy = $user->name . ' (' . ($user->getRoleNames()->first() ?? 'Officer') . ')';

        if ($format === 'pdf') {
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
                'applications' => $rows,
                'masked' => $masked,
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
                'filters' => $filters,
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

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
            'form',
            'fieldValues.formField',
            'documents.formDocument',
            'statusLogs.user',
            'assignments.assignee',
            'assignments.assigner',
        ]);

        $allowedTransitions = $this->allowedTransitions($user, $application);
        $quotaStatus = $this->quotas->status($application->scheme, $application->district_id);

        $fieldOfficers = $user->can('assignFieldOfficer', $application)
            ? User::role('Field Officer')
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

        AccessAuditLog::log('application_viewed', Application::class, (string) $application->id);

        return view('admin.applications.show', compact('application', 'allowedTransitions', 'quotaStatus', 'fieldOfficers'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('view', $application)) {
            abort(403, 'Unauthorized access.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:district_verified,directorate_approved,disbursed,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = $this->allowedTransitions($user, $application);

        if (! in_array($data['status'], $allowed, true)) {
            return back()->with('error', 'You are not authorized to transition this application to the requested status.');
        }

        if (in_array($data['status'], ['directorate_approved', 'disbursed'], true)
            && ! $this->quotas->canApprove($application->scheme, $application->district_id)
            && ! in_array($application->status, ['directorate_approved', 'disbursed'], true)) {
            $quota = $this->quotas->status($application->scheme, $application->district_id);

            return back()->with('error', sprintf(
                'District quota reached (%d / %d). Cannot approve further applications for this district.',
                $quota['used'] ?? 0,
                $quota['quota'] ?? 0
            ));
        }

        DB::transaction(function () use ($application, $data, $user) {
            $from = $application->status;

            $application->status = $data['status'];

            if ($data['status'] === 'rejected') {
                $application->rejection_reason = $data['rejection_reason'] ?? $data['note'] ?? null;
            }

            if (! empty($data['verification_note'])) {
                $application->verification_note = $data['verification_note'];
            } elseif (! empty($data['note']) && $data['status'] !== 'rejected') {
                $application->verification_note = $data['note'];
            }

            $application->save();

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $from,
                'to_status' => $data['status'],
                'user_id' => $user->id,
                'note' => $data['note'] ?? $data['rejection_reason'] ?? null,
            ]);

            AccessAuditLog::log('application_status_updated', Application::class, (string) $application->id, [
                'from' => $from,
                'to' => $data['status'],
                'note' => $data['note'] ?? $data['rejection_reason'] ?? null,
            ]);
        });

        return back()->with('success', 'Application status updated to '.Application::STATUSES[$data['status']].'.');
    }

    public function assignFieldOfficer(Request $request, Application $application): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->can('assignFieldOfficer', $application)) {
            abort(403, 'Unauthorized to assign field officers for this application.');
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'task_type' => ['required', 'in:land_verification,seed_distribution,technical_audit'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $fieldOfficer = User::findOrFail($data['assigned_to']);

        if (! in_array($application->district_id, $fieldOfficer->authorizedDistrictIds(), true)) {
            return back()->with('error', 'Selected Field Officer is not assigned to this district.');
        }

        $assignment = ApplicationAssignment::create([
            'application_id' => $application->id,
            'assigned_to' => $fieldOfficer->id,
            'assigned_by' => $user->id,
            'task_type' => $data['task_type'],
            'status' => ApplicationAssignment::STATUS_ASSIGNED,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        AccessAuditLog::log('field_officer_assigned', Application::class, (string) $application->id, [
            'assignment_id' => $assignment->id,
            'assigned_to' => $fieldOfficer->name,
            'task_type' => $data['task_type'],
        ]);

        return back()->with('success', "Task successfully delegated to Field Officer {$fieldOfficer->name}.");
    }

    public function submitTaskReport(Request $request, ApplicationAssignment $assignment): RedirectResponse
    {
        $user = auth()->user();

        if ($assignment->assigned_to !== $user->id && ! $user->hasRole('Super Admin')) {
            abort(403, 'You are not assigned to this task.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'land_verified_kanals' => ['nullable', 'numeric'],
            'recommendation' => ['nullable', 'in:recommended,not_recommended,needs_further_audit'],
        ]);

        $assignment->status = $data['status'];
        $assignment->notes = $data['notes'] ?? $assignment->notes;
        $assignment->report_data = [
            'land_verified_kanals' => $data['land_verified_kanals'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'submitted_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
        ];

        if ($data['status'] === 'completed') {
            $assignment->completed_at = now();
        }

        $assignment->save();

        AccessAuditLog::log('task_report_submitted', ApplicationAssignment::class, (string) $assignment->id, [
            'status' => $data['status'],
            'application_id' => $assignment->application_id,
        ]);

        return back()->with('success', 'Task report recorded successfully.');
    }

    private function allowedTransitions($user, Application $application): array
    {
        // Technical Super Admin, Secretary, and Division Directors have ZERO default approval authority
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Division Director'])) {
            return [];
        }

        $allowed = [];
        $status = $application->status;

        // District Verification: District DD / District Officer
        if ($status === 'pending' && $user->can('verifyDistrict', $application)) {
            $allowed[] = 'district_verified';
            $allowed[] = 'rejected';
        }

        // Directorate Approval: District DD / Directorate
        if ($status === 'district_verified' && $user->can('approveDirectorate', $application)) {
            $allowed[] = 'directorate_approved';
            $allowed[] = 'rejected';
        }

        // Disbursement: Delivery confirmation / Disbursed
        if ($status === 'directorate_approved' && $user->can('disburse', $application)) {
            $allowed[] = 'disbursed';
            $allowed[] = 'rejected';
        }

        return array_values(array_unique($allowed));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Services\QuotaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationReviewController extends Controller
{
    public function __construct(private QuotaService $quotas) {}

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);
        $departments = Department::orderBy('display_order')->get();
        $districts = District::orderBy('name')->get();
        $schemes = Scheme::orderBy('title')->get(['id', 'title', 'department_id']);
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

    public function export(Request $request): BinaryFileResponse
    {
        $format = $request->string('format', 'xlsx')->toString();
        $rows = $this->filteredQuery($request)->limit(5000)->get();
        $filename = 'alf-applications-'.now()->format('Ymd_His');

        if ($format === 'csv') {
            return Excel::download(new ApplicationsExport($rows), $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new ApplicationsExport($rows), $filename.'.xlsx');
    }

    private function filteredQuery(Request $request)
    {
        $user = auth()->user();
        $query = Application::with(['scheme.department', 'applicant', 'district'])->latest();

        $this->applyRoleScope($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('department_id')) {
            $query->whereHas('scheme', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
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
        $this->authorizeView($application);

        $application->load([
            'scheme.department',
            'applicant.district',
            'district',
            'form',
            'fieldValues.formField',
            'documents.formDocument',
            'statusLogs.user',
        ]);

        $allowedTransitions = $this->allowedTransitions(auth()->user(), $application);
        $quotaStatus = $this->quotas->status($application->scheme, $application->district_id);

        return view('admin.applications.show', compact('application', 'allowedTransitions', 'quotaStatus'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeView($application);

        $data = $request->validate([
            'status' => ['required', 'in:district_verified,directorate_approved,disbursed,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
            'verification_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        $allowed = $this->allowedTransitions($user, $application);

        if (! in_array($data['status'], $allowed, true)) {
            return back()->with('error', 'You are not allowed to set this status.');
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
        });

        return back()->with('success', 'Application status updated to '.Application::STATUSES[$data['status']].'.');
    }

    private function applyRoleScope($query, $user): void
    {
        if ($user->hasRole('District Officer')) {
            $query->where('district_id', $user->district_id);
        } elseif ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            if ($user->department_id) {
                $query->whereHas('scheme', fn ($q) => $q->where('department_id', $user->department_id));
            }
        }
    }

    private function authorizeView(Application $application): void
    {
        $user = auth()->user();

        if ($user->hasRole('District Officer') && $application->district_id !== $user->district_id) {
            abort(403);
        }

        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            if ($user->department_id && $application->scheme->department_id !== $user->department_id) {
                abort(403);
            }
        }
    }

    private function allowedTransitions($user, Application $application): array
    {
        $status = $application->status;

        if ($user->hasAnyRole(['Super Admin', 'Secretary'])) {
            return match ($status) {
                'pending' => ['district_verified', 'rejected'],
                'district_verified' => ['directorate_approved', 'rejected'],
                'directorate_approved' => ['disbursed', 'rejected'],
                default => [],
            };
        }

        if ($user->hasRole('District Officer') && $status === 'pending') {
            return ['district_verified', 'rejected'];
        }

        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            return match ($status) {
                'district_verified' => ['directorate_approved', 'rejected'],
                'directorate_approved' => ['disbursed', 'rejected'],
                default => [],
            };
        }

        return [];
    }
}

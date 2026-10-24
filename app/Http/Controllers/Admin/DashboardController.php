<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $applicationQuery = Application::query();
        $scopedDistrict = null;

        $departmentsPerformance = collect();
        $allSchemes = collect();

        if ($user->isDistrictScopedOfficer()) {
            $userDistricts = $user->authorizedDistrictIds();
            $applicationQuery->whereIn('district_id', $userDistricts);
            $scopedDistrict = District::whereIn('id', $userDistricts)->first();

            $statusCounts = (clone $applicationQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $schemesCount = Scheme::whereIn('scope', ['gb', 'province_wide'])
                ->orWhereHas('districts', fn ($q) => $q->whereIn('districts.id', $userDistricts))
                ->count();

            $departmentsCount = Department::whereHas('schemes', function ($sq) use ($userDistricts) {
                $sq->whereIn('scope', ['gb', 'province_wide'])
                    ->orWhereHas('districts', fn ($dq) => $dq->whereIn('districts.id', $userDistricts));
            })->count();

            $usersCount = User::whereIn('district_id', $userDistricts)
                ->orWhereHas('officeAssignments', fn ($q) => $q->active()->whereIn('district_id', $userDistricts))
                ->count();

            $kpis = [
                'departments' => $departmentsCount,
                'districts' => count($userDistricts),
                'schemes' => $schemesCount,
                'applicants' => Applicant::whereIn('district_id', $userDistricts)->count(),
                'applications' => (clone $applicationQuery)->count(),
                'users' => $usersCount,
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'district_verified' => (int) ($statusCounts['district_verified'] ?? 0),
                'directorate_approved' => (int) ($statusCounts['directorate_approved'] ?? 0),
                'disbursed' => (int) ($statusCounts['disbursed'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ];
        } else {
            // Directors, Secretary, and Super Admin
            if ($user->hasRole('Division Director')) {
                $divisionDistricts = $user->authorizedDistrictIds();
                $applicationQuery->whereIn('district_id', $divisionDistricts);
            } elseif ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']) && $user->department_id) {
                $applicationQuery->whereHas('scheme', fn ($q) => $q->where('department_id', $user->department_id));
            }

            $statusCounts = (clone $applicationQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $kpis = [
                'departments' => Department::count(),
                'districts' => District::count(),
                'schemes' => Scheme::count(),
                'applicants' => Applicant::count(),
                'applications' => (clone $applicationQuery)->count(),
                'users' => User::count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'district_verified' => (int) ($statusCounts['district_verified'] ?? 0),
                'directorate_approved' => (int) ($statusCounts['directorate_approved'] ?? 0),
                'disbursed' => (int) ($statusCounts['disbursed'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ];

            // Compute performance matrix across all 3 departments and their projects/schemes
            $allDepts = Department::orderBy('display_order')->get();
            $schemes = Scheme::with('department')->orderBy('department_id')->orderBy('display_order')->get();

            $schemeStats = Application::query()
                ->selectRaw('scheme_id, status, current_stage, COUNT(*) as count')
                ->groupBy('scheme_id', 'status', 'current_stage')
                ->get();

            $interestCounts = \App\Models\SchemeInterest::query()
                ->selectRaw('scheme_id, COUNT(*) as count')
                ->groupBy('scheme_id')
                ->pluck('count', 'scheme_id');

            $allSchemes = $schemes->map(function ($scheme) use ($schemeStats) {
                $matching = $schemeStats->where('scheme_id', $scheme->id);
                $total = $matching->sum('count');

                $pending = $matching->filter(function ($row) {
                    return in_array($row->status, ['pending', 'submitted', 'under_review'], true)
                        || in_array($row->current_stage, ['submitted', 'under_review', 'field_survey_assigned', 'visit_scheduled', 'visit_completed', 'findings_submitted', 'correction_required'], true);
                })->sum('count');

                $completed = $matching->filter(function ($row) {
                    return in_array($row->status, ['directorate_approved', 'district_verified', 'disbursed'], true)
                        || in_array($row->current_stage, ['approved', 'challan_issued', 'payment_proof_submitted', 'payment_verified', 'eligible_beneficiary', 'stock_allocated', 'partially_distributed', 'fully_distributed', 'disbursed', 'completed'], true);
                })->sum('count');

                $disbursed = $matching->filter(function ($row) {
                    return $row->status === 'disbursed'
                        || in_array($row->current_stage, ['partially_distributed', 'fully_distributed', 'disbursed', 'completed'], true);
                })->sum('count');

                $rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

                return [
                    'id' => $scheme->id,
                    'title' => $scheme->title,
                    'slug' => $scheme->slug,
                    'department_id' => $scheme->department_id,
                    'department_name' => $scheme->department?->name ?? 'Agriculture',
                    'department_slug' => $scheme->department?->slug ?? 'agriculture',
                    'type' => $scheme->type ?: 'running',
                    'status' => $scheme->status ?: 'active',
                    'scope' => $scheme->scope ?: 'gb',
                    'total_applications' => $total,
                    'pending_applications' => $pending,
                    'completed_applications' => $completed,
                    'disbursed_applications' => $disbursed,
                    'completion_rate' => $rate,
                ];
            });

            $departmentsPerformance = $allDepts->map(function ($dept) use ($allSchemes, $interestCounts) {
                $deptSchemes = $allSchemes->where('department_id', $dept->id);
                $total = $deptSchemes->sum('total_applications');
                $pending = $deptSchemes->sum('pending_applications');
                $completed = $deptSchemes->sum('completed_applications');
                $disbursed = $deptSchemes->sum('disbursed_applications');
                $schemeIds = $deptSchemes->pluck('id')->all();
                $interests = $interestCounts->filter(fn ($v, $k) => in_array($k, $schemeIds, true))->sum();

                $resolutionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 100.0;

                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'name_ur' => $dept->name_ur,
                    'slug' => $dept->slug,
                    'icon' => $dept->icon ?: 'fa-solid fa-landmark',
                    'schemes_count' => $deptSchemes->count(),
                    'active_schemes_count' => $deptSchemes->where('status', 'active')->count(),
                    'total_applications' => $total,
                    'pending_applications' => $pending,
                    'completed_applications' => $completed,
                    'disbursed_applications' => $disbursed,
                    'resolution_rate' => $resolutionRate,
                    'interests_count' => $interests,
                ];
            });
        }

        $recentApplications = (clone $applicationQuery)
            ->with(['scheme.department', 'applicant', 'district'])
            ->latest()
            ->limit(10)
            ->get();

        $isSecretary = $user->hasRole('Secretary');
        $isSuperAdmin = $user->hasRole('Super Admin');

        return view('admin.dashboard.index', compact(
            'kpis',
            'recentApplications',
            'scopedDistrict',
            'departmentsPerformance',
            'allSchemes',
            'isSecretary',
            'isSuperAdmin'
        ));
    }
}

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
        }

        $recentApplications = (clone $applicationQuery)
            ->with(['scheme.department', 'applicant', 'district'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard.index', compact('kpis', 'recentApplications', 'scopedDistrict'));
    }
}

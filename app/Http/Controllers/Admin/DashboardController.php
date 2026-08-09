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

        if ($user->hasRole('District Officer') && $user->district_id) {
            $applicationQuery->where('district_id', $user->district_id);
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

        $recentApplications = (clone $applicationQuery)
            ->with(['scheme.department', 'applicant', 'district'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard.index', compact('kpis', 'recentApplications'));
    }
}

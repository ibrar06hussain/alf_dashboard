<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CmDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $departments = $this->departmentsFor($user);
        $districts = District::orderBy('name')->get();
        $schemes = Scheme::query()
            ->when($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']) && $user->department_id,
                fn ($q) => $q->where('department_id', $user->department_id))
            ->orderBy('title')
            ->get(['id', 'title', 'department_id']);

        $filters = $this->filters($request);
        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']) && $user->department_id) {
            $filters['department_id'] = $user->department_id;
        }

        return view('admin.cm-dashboard.index', compact('departments', 'districts', 'schemes', 'filters'));
    }

    public function analytics(Request $request)
    {
        $query = $this->filteredQuery($request);

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byDistrict = (clone $query)
            ->join('districts', 'districts.id', '=', 'applications.district_id')
            ->selectRaw('districts.name as label, COUNT(*) as total')
            ->groupBy('districts.name')
            ->orderByDesc('total')
            ->get();

        $byDepartment = (clone $query)
            ->join('schemes', 'schemes.id', '=', 'applications.scheme_id')
            ->join('departments', 'departments.id', '=', 'schemes.department_id')
            ->selectRaw('departments.name as label, COUNT(*) as total')
            ->groupBy('departments.name')
            ->orderByDesc('total')
            ->get();

        $driver = $query->getConnection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', COALESCE(submitted_at, created_at))"
            : "DATE_FORMAT(COALESCE(submitted_at, created_at), '%Y-%m')";

        $byMonth = (clone $query)
            ->selectRaw("{$monthExpr} as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        $totals = [
            'applications' => (clone $query)->count(),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'district_verified' => (int) ($byStatus['district_verified'] ?? 0),
            'directorate_approved' => (int) ($byStatus['directorate_approved'] ?? 0),
            'disbursed' => (int) ($byStatus['disbursed'] ?? 0),
            'rejected' => (int) ($byStatus['rejected'] ?? 0),
        ];

        return response()->json([
            'totals' => $totals,
            'by_status' => [
                'labels' => array_values(Application::STATUSES),
                'keys' => array_keys(Application::STATUSES),
                'series' => collect(array_keys(Application::STATUSES))
                    ->map(fn ($key) => (int) ($byStatus[$key] ?? 0))
                    ->values(),
            ],
            'by_district' => [
                'labels' => $byDistrict->pluck('label'),
                'series' => $byDistrict->pluck('total'),
            ],
            'by_department' => [
                'labels' => $byDepartment->pluck('label'),
                'series' => $byDepartment->pluck('total'),
            ],
            'by_month' => [
                'labels' => $byMonth->pluck('label'),
                'series' => $byMonth->pluck('total'),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse|\Illuminate\Http\Response
    {
        $format = $request->string('format', 'xlsx')->toString();
        $rows = $this->filteredQuery($request)
            ->with(['scheme.department', 'applicant', 'district'])
            ->latest()
            ->get();

        $filename = 'alf-applications-'.now()->format('Ymd_His');

        if ($format === 'csv') {
            return Excel::download(new ApplicationsExport($rows), $filename.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.cm-dashboard.export-pdf', [
                'applications' => $rows,
                'filters' => $this->filters($request),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(new ApplicationsExport($rows), $filename.'.xlsx');
    }

    private function filteredQuery(Request $request)
    {
        $user = auth()->user();
        $query = Application::query();

        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']) && $user->department_id) {
            $query->whereHas('scheme', fn ($q) => $q->where('department_id', $user->department_id));
        } elseif ($request->filled('department_id')) {
            $query->whereHas('scheme', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->date('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->date('to_date'));
        }

        return $query;
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'department_id', 'district_id', 'scheme_id', 'status', 'from_date', 'to_date',
        ]);
    }

    private function departmentsFor($user)
    {
        if ($user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']) && $user->department_id) {
            return Department::where('id', $user->department_id)->get();
        }

        return Department::orderBy('display_order')->get();
    }
}

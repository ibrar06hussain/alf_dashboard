<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentStat;
use App\Services\StatsAggregationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentStatController extends Controller
{
    public function index(Request $request): View
    {
        $query = DepartmentStat::with('department')->orderBy('display_order');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $departments = Department::orderBy('display_order')->get();

        $pageStats = [
            ['label' => 'Stat rows', 'value' => (clone $query)->count(), 'icon' => 'fa-solid fa-chart-simple', 'tone' => 'green'],
            ['label' => 'Active', 'value' => (clone $query)->where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'Manual', 'value' => (clone $query)->where('source', 'manual')->count(), 'icon' => 'fa-solid fa-pen', 'tone' => 'blue'],
            ['label' => 'Auto', 'value' => (clone $query)->where('source', 'auto')->count(), 'icon' => 'fa-solid fa-rotate', 'tone' => 'amber'],
        ];

        $stats = $query->paginate(30)->withQueryString();

        return view('admin.stats.index', compact('stats', 'departments', 'pageStats'));
    }

    public function refresh(StatsAggregationService $service): RedirectResponse
    {
        $count = $service->refreshAll();

        return back()->with('success', "Refreshed {$count} auto statistic(s).");
    }

    public function create(Request $request): View
    {
        $departments = Department::orderBy('display_order')->get();
        $selectedDepartmentId = $request->integer('department_id') ?: null;
        $metrics = StatsAggregationService::METRICS;

        return view('admin.stats.create', compact('departments', 'selectedDepartmentId', 'metrics'));
    }

    public function store(Request $request, StatsAggregationService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);

        if (($data['source'] ?? 'manual') === 'auto' && ! empty($data['auto_metric'])) {
            $data['stat_value'] = (string) $service->compute((int) $data['department_id'], $data['auto_metric']);
        }

        DepartmentStat::create($data);

        return redirect()
            ->route('admin.stats.index', ['department_id' => $data['department_id']])
            ->with('success', 'Stat created.');
    }

    public function edit(DepartmentStat $stat): View
    {
        $departments = Department::orderBy('display_order')->get();
        $metrics = StatsAggregationService::METRICS;

        return view('admin.stats.edit', compact('stat', 'departments', 'metrics'));
    }

    public function update(Request $request, DepartmentStat $stat, StatsAggregationService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');

        if (($data['source'] ?? 'manual') === 'auto' && ! empty($data['auto_metric'])) {
            $data['stat_value'] = (string) $service->compute((int) $data['department_id'], $data['auto_metric']);
        }

        $stat->update($data);

        return redirect()
            ->route('admin.stats.index', ['department_id' => $stat->department_id])
            ->with('success', 'Stat updated.');
    }

    public function destroy(DepartmentStat $stat): RedirectResponse
    {
        $departmentId = $stat->department_id;
        $stat->delete();

        return redirect()
            ->route('admin.stats.index', ['department_id' => $departmentId])
            ->with('success', 'Stat deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'stat_label' => ['required', 'string', 'max:255'],
            'stat_value' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'source' => ['required', 'in:manual,auto'],
            'auto_metric' => ['nullable', 'required_if:source,auto', 'string', 'max:100'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);
    }
}

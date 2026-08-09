@extends('layouts.admin')

@section('title', 'Department Stats')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Department Stats',
    'subtitle' => 'Public portal metrics — manual entries and auto-aggregated values.',
    'icon' => 'fa-solid fa-chart-simple',
    'actions' => [
        ['label' => 'Add Stat', 'url' => route('admin.stats.create', request()->only('department_id')), 'class' => 'btn btn-primary'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
            <form method="GET" class="alf-filters row g-2 flex-grow-1 mb-0">
                <div class="col-md-6">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
            <form action="{{ route('admin.stats.refresh') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-info">Refresh Auto Stats</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table alf-table">
                <thead>
                <tr>
                    <th>Order</th>
                    <th>Department</th>
                    <th>Label</th>
                    <th>Value</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($stats as $stat)
                    <tr>
                        <td>{{ $stat->display_order }}</td>
                        <td>{{ $stat->department?->name }}</td>
                        <td><strong>{{ $stat->stat_label }}</strong></td>
                        <td>{{ $stat->stat_value }}</td>
                        <td><span class="alf-badge alf-badge--{{ $stat->source === 'auto' ? 'amber' : 'blue' }}">{{ $stat->source }}{{ $stat->auto_metric ? ' / '.$stat->auto_metric : '' }}</span></td>
                        <td>
                            <span class="alf-badge alf-badge--{{ $stat->is_active ? 'green' : 'slate' }}">
                                {{ $stat->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="alf-actions">
                                <a href="{{ route('admin.stats.edit', $stat) }}" class="btn btn-sm btn-primary">Edit</a>
                                <form action="{{ route('admin.stats.destroy', $stat) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete stat?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="alf-empty">No stats found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $stats->links() }}</div>
    </div>
</div>
@endsection

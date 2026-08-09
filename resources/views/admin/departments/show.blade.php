@extends('layouts.admin')

@section('title', $department->name)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $department->name,
    'subtitle' => 'Department profile · slug: '.$department->slug,
    'icon' => $department->icon ?: 'fa-solid fa-landmark',
    'actions' => [
        ['label' => 'Edit', 'url' => route('admin.departments.edit', $department), 'class' => 'btn btn-primary'],
        ['label' => 'Stats', 'url' => route('admin.stats.index', ['department_id' => $department->id]), 'class' => 'btn btn-info'],
        ['label' => 'Back', 'url' => route('admin.departments.index'), 'class' => 'btn btn-light'],
    ],
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Status', 'value' => $department->is_active ? 'Active' : 'Inactive', 'icon' => 'fa-solid fa-circle-check', 'tone' => $department->is_active ? 'green' : 'slate'],
    ['label' => 'Schemes', 'value' => $department->schemes->count(), 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
    ['label' => 'Stat rows', 'value' => $department->stats->count(), 'icon' => 'fa-solid fa-chart-simple', 'tone' => 'amber'],
    ['label' => 'Order', 'value' => $department->display_order, 'icon' => 'fa-solid fa-sort', 'tone' => 'teal'],
]])

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">About</div>
            <div class="card-body">
                <h5 class="fw-bold">Vision</h5>
                <p>{{ $department->vision ?: '—' }}</p>
                <h5 class="fw-bold">Mission</h5>
                <p>{{ $department->mission ?: '—' }}</p>
                <h5 class="fw-bold">Objectives</h5>
                <pre class="mb-0" style="white-space: pre-wrap;font-family:inherit;">{{ $department->objectives ?: '—' }}</pre>
                @if($department->leadership_info)
                    <h5 class="fw-bold mt-3">Leadership</h5>
                    <p class="mb-0" style="white-space:pre-wrap;">{{ $department->leadership_info }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card alf-panel mb-3">
            <div class="card-header">Public Stats</div>
            <ul class="list-group list-group-flush">
                @forelse($department->stats as $stat)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $stat->stat_label }}</span>
                        <strong>{{ $stat->stat_value }}</strong>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No stats</li>
                @endforelse
            </ul>
        </div>
        <div class="card alf-panel mb-3">
            <div class="card-header">Schemes ({{ $department->schemes->count() }})</div>
            <ul class="list-group list-group-flush">
                @forelse($department->schemes->take(12) as $scheme)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <a href="{{ route('admin.schemes.show', $scheme) }}">{{ $scheme->title }}</a>
                        <span class="alf-badge alf-badge--{{ $scheme->type === 'proposed' ? 'amber' : 'green' }}">{{ $scheme->typeLabel() }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No schemes</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

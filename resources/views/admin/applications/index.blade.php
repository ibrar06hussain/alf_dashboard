@extends('layouts.admin')

@section('title', 'Applications')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Applications',
    'subtitle' => 'Review, filter, and track applications across schemes and districts.',
    'icon' => 'fa-solid fa-folder',
    'actions' => [
        ['label' => 'Export Excel', 'url' => route('admin.applications.export', array_merge(request()->query(), ['format' => 'xlsx'])), 'class' => 'btn btn-success'],
        ['label' => 'Export CSV', 'url' => route('admin.applications.export', array_merge(request()->query(), ['format' => 'csv'])), 'class' => 'btn btn-info text-white'],
        ['label' => 'Export PDF', 'url' => route('admin.applications.export', array_merge(request()->query(), ['format' => 'pdf'])), 'class' => 'btn btn-danger'],
    ],
])

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-3">
    <div class="card-body">
        <form method="GET" class="alf-filters mb-3">
            {{-- Row 1: Primary Filters --}}
            <div class="row g-2 mb-2">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-semibold">Search Applicant / App No</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="App no / Name / CNIC / Phone / Village">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Application Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">District</label>
                    <select name="district_id" class="form-select form-select-sm">
                        <option value="">All districts</option>
                        @foreach($districts as $district)
                            <option value="{{ $district->id }}" @selected(request('district_id') == $district->id)>{{ $district->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1 fw-semibold">Scheme</label>
                    <select name="scheme_id" class="form-select form-select-sm">
                        <option value="">All schemes</option>
                        @foreach($schemes as $scheme)
                            <option value="{{ $scheme->id }}" @selected(request('scheme_id') == $scheme->id)>{{ $scheme->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 2: Land & Seed Bags Filter Options --}}
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Min Land (Kanals)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-ruler-combined"></i></span>
                        <input type="number" step="any" min="0" name="min_land" class="form-control" value="{{ request('min_land') }}" placeholder="e.g. 10">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Max Land (Kanals)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-ruler"></i></span>
                        <input type="number" step="any" min="0" name="max_land" class="form-control" value="{{ request('max_land') }}" placeholder="e.g. 40">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Min Bags Required</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-wheat-awn"></i></span>
                        <input type="number" min="1" name="min_bags" class="form-control" value="{{ request('min_bags') }}" placeholder="e.g. 2">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1 fw-semibold">Max Bags Required</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-wheat-awn"></i></span>
                        <input type="number" min="1" name="max_bags" class="form-control" value="{{ request('max_bags') }}" placeholder="e.g. 10">
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Apply Filters
                    </button>
                    @if(request()->hasAny(['q', 'status', 'department_id', 'district_id', 'scheme_id', 'min_land', 'max_land', 'min_bags', 'max_bags']))
                        <a href="{{ route('admin.applications.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table alf-table align-middle">
                <thead>
                <tr>
                    <th>App No</th>
                    <th>Applicant</th>
                    <th>Scheme</th>
                    <th>Department</th>
                    <th>District</th>
                    <th class="text-end">Available Land</th>
                    <th class="text-center">Bags Required</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($applications as $application)
                    @php
                        $land = $application->getAvailableLandKanals();
                        $bags = $application->getBagsRequired();
                    @endphp
                    <tr>
                        <td><strong>{{ $application->application_no }}</strong></td>
                        <td>
                            <div class="fw-semibold">{{ $application->applicant?->full_name ?? '—' }}</div>
                            <div class="text-muted small">
                                <i class="fa-regular fa-id-card me-1"></i>{{ $application->applicant?->cnic ?? '—' }}
                            </div>
                        </td>
                        <td>{{ $application->scheme?->title ?? '—' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $application->scheme?->department?->name ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $application->district?->name ?? '—' }}</div>
                            @if($application->applicant?->tehsil)
                                <div class="text-muted small">
                                    {{ $application->applicant->tehsil }}@if($application->applicant?->village) · {{ $application->applicant->village }}@endif
                                </div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($land !== null)
                                <span class="fw-bold text-dark">{{ number_format($land, 1) }}</span>
                                <span class="text-muted small">Kanals</span>
                            @else
                                <span class="text-muted opacity-50">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($bags !== null)
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="fa-solid fa-wheat-awn me-1"></i>{{ $bags }} bags
                                </span>
                            @else
                                <span class="text-muted opacity-50">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="alf-badge alf-badge--slate">
                                {{ $statuses[$application->status] ?? $application->status }}
                            </span>
                        </td>
                        <td>
                            <span class="small text-muted">
                                {{ $application->submitted_at?->format('d M Y') ?: $application->created_at?->format('d M Y') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.applications.show', $application) }}" class="btn btn-sm btn-primary">
                                Review
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="alf-empty py-4 text-center text-muted">
                                <i class="fa-regular fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                                No applications found matching the selected filters.
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $applications->links() }}</div>
    </div>
</div>
@endsection

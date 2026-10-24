@extends('layouts.admin')

@section('title', $scopedDistrict ? ($scopedDistrict->name . ' District Dashboard') : ($isSecretary ? 'Secretary Executive Dashboard' : 'Dashboard'))

@section('content')
@php
    $heroActions = [];
    if ($isSecretary || (! $scopedDistrict && ($isSuperAdmin || auth()->user()->hasRole('Division Director')))) {
        $heroActions = [
            ['label' => 'CM Dashboard', 'url' => route('admin.cm-dashboard.index'), 'class' => 'btn btn-primary', 'icon' => 'fa-solid fa-chart-line'],
            ['label' => 'Wheat Seed MIS', 'url' => route('admin.wheat-mis.index'), 'class' => 'btn btn-success', 'icon' => 'fa-solid fa-wheat-awn'],
            ['label' => 'Citizen Interests', 'url' => route('admin.interests.index'), 'class' => 'btn btn-info text-white', 'icon' => 'fa-solid fa-bell'],
            ['label' => 'Applications', 'url' => route('admin.applications.index'), 'class' => 'btn btn-dark', 'icon' => 'fa-solid fa-folder'],
        ];
    }
@endphp

@include('admin.partials.page-hero', [
    'title' => $scopedDistrict
        ? ($scopedDistrict->name . ' District Dashboard')
        : ($isSecretary ? 'Secretary Executive Dashboard' : 'Dashboard'),
    'subtitle' => $scopedDistrict
        ? ('Overview of ALF schemes, applications, and statistics for ' . $scopedDistrict->name . ' District.')
        : ($isSecretary ? 'Cross-departmental project monitoring, applications tracking, and performance oversight for Agriculture, Livestock & Fisheries.' : 'Overview of ALF schemes, applications, and performance.'),
    'icon' => $scopedDistrict ? 'fa-solid fa-location-dot' : ($isSecretary ? 'fa-solid fa-briefcase' : 'fa-solid fa-rocket'),
    'actions' => $heroActions,
])

@if($scopedDistrict)
    <div class="alert alert-info d-flex align-items-center mb-3 shadow-sm border-0" role="alert" style="background: linear-gradient(135deg, #e0f2fe 0%, #f0fdf4 100%); color: #0369a1; border-left: 4px solid #0284c7 !important;">
        <i class="fa-solid fa-building-user fa-lg me-3 text-primary"></i>
        <div>
            <strong>District Officer Scope Active:</strong> You are viewing applications, metrics, and statistics scoped strictly to <strong>{{ $scopedDistrict->name }} District</strong>.
        </div>
    </div>
@endif

{{-- Top KPI Cards --}}
@include('admin.partials.stats', ['stats' => [
    ['label' => $scopedDistrict ? 'District' : 'Total Districts', 'value' => $scopedDistrict ? $scopedDistrict->name : $kpis['districts'], 'icon' => 'fa-solid fa-location-dot', 'tone' => 'green'],
    ['label' => 'Active Schemes', 'value' => $kpis['schemes'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
    ['label' => 'Total Applications', 'value' => number_format($kpis['applications']), 'icon' => 'fa-solid fa-folder', 'tone' => 'amber'],
    ['label' => $scopedDistrict ? 'District Applicants' : 'Registered Citizens', 'value' => number_format($scopedDistrict ? $kpis['applicants'] : $kpis['applicants']), 'icon' => 'fa-solid fa-users', 'tone' => 'slate'],
]])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Pending Review / Survey', 'value' => number_format($kpis['pending']), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
    ['label' => 'District Verified', 'value' => number_format($kpis['district_verified']), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'blue'],
    ['label' => 'Directorate Approved', 'value' => number_format($kpis['directorate_approved']), 'icon' => 'fa-solid fa-thumbs-up', 'tone' => 'teal'],
    ['label' => 'Completed / Disbursed', 'value' => number_format($kpis['disbursed']), 'icon' => 'fa-solid fa-hand-holding-dollar', 'tone' => 'green'],
]])

{{-- Department-wise Performance Cards (Secretary & Global Executive View) --}}
@if(!empty($departmentsPerformance) && $departmentsPerformance->isNotEmpty())
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-landmark text-primary me-2"></i>Department Performance & Oversight
                </h5>
                <small class="text-muted">Applications received, pending, completed, and resolution rate for all three departments.</small>
            </div>
            <span class="badge bg-light text-dark border px-3 py-2">
                <i class="fa-solid fa-check-double text-success me-1"></i> 3 Core Sectors
            </span>
        </div>

        <div class="row g-3">
            @foreach($departmentsPerformance as $dept)
                @php
                    $toneClass = match($dept['slug']) {
                        'agriculture' => 'border-top-success',
                        'livestock' => 'border-top-warning',
                        'fisheries' => 'border-top-info',
                        default => 'border-top-primary',
                    };
                    $badgeBg = match($dept['slug']) {
                        'agriculture' => 'bg-success',
                        'livestock' => 'bg-warning text-dark',
                        'fisheries' => 'bg-info text-dark',
                        default => 'bg-primary',
                    };
                    $progressColor = match($dept['slug']) {
                        'agriculture' => 'bg-success',
                        'livestock' => 'bg-warning',
                        'fisheries' => 'bg-info',
                        default => 'bg-primary',
                    };
                @endphp
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0" style="border-top: 4px solid var(--bs-primary) !important; {{ $dept['slug'] === 'agriculture' ? 'border-top-color: #16a34a !important;' : ($dept['slug'] === 'livestock' ? 'border-top-color: #d97706 !important;' : 'border-top-color: #0284c7 !important;') }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ $dept['name'] }}</h6>
                                    @if(!empty($dept['name_ur']))
                                        <small class="text-muted" style="font-size: 11px;">{{ $dept['name_ur'] }}</small>
                                    @endif
                                </div>
                                <span class="badge {{ $badgeBg }} rounded-pill px-2 py-1">
                                    {{ $dept['schemes_count'] }} {{ \Illuminate\Support\Str::plural('Scheme', $dept['schemes_count']) }}
                                </span>
                            </div>

                            <hr class="my-2 opacity-25">

                            {{-- Stats grid inside department card --}}
                            <div class="row g-2 text-center my-2">
                                <div class="col-4">
                                    <div class="p-2 bg-light rounded">
                                        <small class="text-muted d-block" style="font-size: 10px;">Received</small>
                                        <strong class="fs-6 text-dark">{{ number_format($dept['total_applications']) }}</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-light rounded">
                                        <small class="text-muted d-block" style="font-size: 10px;">Pending</small>
                                        <strong class="fs-6 text-warning">{{ number_format($dept['pending_applications']) }}</strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-light rounded">
                                        <small class="text-muted d-block" style="font-size: 10px;">Completed</small>
                                        <strong class="fs-6 text-success">{{ number_format($dept['completed_applications']) }}</strong>
                                    </div>
                                </div>
                            </div>

                            {{-- Resolution Progress Bar --}}
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">Resolution Rate</span>
                                    <strong class="text-dark">{{ $dept['resolution_rate'] }}%</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $progressColor }}" role="progressbar" style="width: {{ $dept['resolution_rate'] }}%;" aria-valuenow="{{ $dept['resolution_rate'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>

                            @if($dept['interests_count'] > 0)
                                <div class="mt-2 text-end">
                                    <small class="text-muted">
                                        <i class="fa-solid fa-bell text-info me-1"></i>
                                        <strong>{{ $dept['interests_count'] }}</strong> Citizen {{ \Illuminate\Support\Str::plural('Interest', $dept['interests_count']) }}
                                    </small>
                                </div>
                            @endif

                            <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                <a href="{{ route('admin.applications.index', ['department_id' => $dept['id']]) }}" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fa-solid fa-list me-1"></i> View Applications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- Projects & Schemes Status Table across All Three Departments --}}
@if(!empty($allSchemes) && $allSchemes->isNotEmpty())
    <div class="card alf-panel shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
            <div>
                <span class="fw-bold text-dark fs-6">
                    <i class="fa-solid fa-folder-tree text-success me-2"></i>Projects & Schemes of All Three Departments
                </span>
                <span class="text-muted ms-2 small d-none d-md-inline">Applications received, pending review, completed, and progress.</span>
            </div>
            <div>
                <a href="{{ route('admin.applications.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-table me-1"></i> All Applications Register
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table alf-table align-middle mb-0 table-hover">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 20px;">#</th>
                        <th>Department</th>
                        <th>Scheme / Project Title</th>
                        <th style="width: 100px;">Scope</th>
                        <th style="width: 90px;" class="text-center">Status</th>
                        <th style="width: 100px;" class="text-end">Received</th>
                        <th style="width: 100px;" class="text-end">Pending</th>
                        <th style="width: 100px;" class="text-end">Completed</th>
                        <th style="width: 120px;">Completion</th>
                        <th style="width: 130px;" class="text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($allSchemes as $index => $scheme)
                        @php
                            $deptBadge = match($scheme['department_slug']) {
                                'agriculture' => 'badge bg-success-subtle text-success border border-success-subtle',
                                'livestock' => 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                'fisheries' => 'badge bg-info-subtle text-info-emphasis border border-info-subtle',
                                default => 'badge bg-light text-dark border',
                            };
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $index + 1 }}</td>
                            <td>
                                <span class="{{ $deptBadge }}">
                                    {{ $scheme['department_name'] }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $scheme['title'] }}</div>
                                <small class="text-muted">{{ ucfirst($scheme['type']) }} Scheme</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border text-uppercase" style="font-size: 10px;">
                                    {{ $scheme['scope'] }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($scheme['status'] === 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($scheme['status']) }}</span>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-dark">
                                {{ number_format($scheme['total_applications']) }}
                            </td>
                            <td class="text-end fw-semibold text-warning">
                                {{ number_format($scheme['pending_applications']) }}
                            </td>
                            <td class="text-end fw-bold text-success">
                                {{ number_format($scheme['completed_applications']) }}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $scheme['completion_rate'] }}%;" aria-valuenow="{{ $scheme['completion_rate'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted fw-bold" style="font-size: 10px;">{{ $scheme['completion_rate'] }}%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.applications.index', ['scheme_id' => $scheme['id']]) }}" class="btn btn-sm btn-light border px-2 py-1" title="Filter applications for this scheme">
                                    <i class="fa-solid fa-arrow-right me-1"></i> Applications
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

{{-- Quick Executive Tools Section for Secretary --}}
@if($isSecretary || $isSuperAdmin)
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); color: #fff;">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 bg-white bg-opacity-20 me-3">
                                <i class="fa-solid fa-chart-line fa-2x text-white"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">CM Dashboard</h5>
                                <small class="text-white text-opacity-75">Executive Analytics</small>
                            </div>
                        </div>
                        <p class="text-white text-opacity-90 small mb-3">
                            Monitor cross-district comparisons, departmental distribution breakdown, submission timelines, and executive KPIs for Chief Minister reporting.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('admin.cm-dashboard.index') }}" class="btn btn-light btn-sm text-dark fw-bold w-100">
                            <i class="fa-solid fa-gauge me-1"></i> Open CM Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(135deg, #166534 0%, #14532d 100%); color: #fff;">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 bg-white bg-opacity-20 me-3">
                                <i class="fa-solid fa-wheat-awn fa-2x text-white"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">Wheat Seed MIS</h5>
                                <small class="text-white text-opacity-75">50:50 Cost Sharing</small>
                            </div>
                        </div>
                        <p class="text-white text-opacity-90 small mb-3">
                            Manage the subsidized wheat seed beneficiary registry, district delivery rates, demand vs. quota gaps, and verify field distribution.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('admin.wheat-mis.index') }}" class="btn btn-light btn-sm text-dark fw-bold w-100">
                            <i class="fa-solid fa-list-check me-1"></i> Open Wheat Seed MIS
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0" style="background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%); color: #fff;">
                <div class="card-body d-flex flex-column justify-content-between p-4">
                    <div>
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 bg-white bg-opacity-20 me-3">
                                <i class="fa-solid fa-bell fa-2x text-white"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">Citizen Interests</h5>
                                <small class="text-white text-opacity-75">Public Demand Tracking</small>
                            </div>
                        </div>
                        <p class="text-white text-opacity-90 small mb-3">
                            Analyze public expressions of interest and feedback submitted across all three departments to plan future projects and budget allocations.
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('admin.interests.index') }}" class="btn btn-light btn-sm text-dark fw-bold w-100">
                            <i class="fa-solid fa-arrow-right me-1"></i> View Citizen Interests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Recent Applications Table --}}
<div class="card alf-panel shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
        <span class="fw-bold text-dark fs-6">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Applications Stream {{ $scopedDistrict ? '(' . $scopedDistrict->name . ' District Only)' : '' }}
        </span>
        <a href="{{ route('admin.applications.index') }}" class="btn btn-sm btn-light border">
            <i class="fa-solid fa-eye me-1"></i> View all
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table align-middle mb-0 table-hover">
                <thead class="table-light">
                <tr>
                    <th>App No</th>
                    <th>Applicant</th>
                    <th>Department / Scheme</th>
                    <th>District</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentApplications as $app)
                    <tr>
                        <td><strong>{{ $app->application_no }}</strong></td>
                        <td>
                            <div class="fw-bold text-dark">{{ $app->applicant?->full_name ?? '—' }}</div>
                            <small class="text-muted">{{ \App\Support\Cnic::dashed($app->applicant?->cnic) }}</small>
                        </td>
                        <td>
                            <div class="text-dark">{{ $app->scheme?->title ?? '—' }}</div>
                            <small class="text-muted">{{ $app->scheme?->department?->name ?? 'Agriculture' }}</small>
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $app->district?->name ?? '—' }}</span></td>
                        <td>
                            @php
                                $statusClass = match($app->status) {
                                    'directorate_approved', 'district_verified', 'approved' => 'badge bg-success',
                                    'disbursed', 'fully_distributed' => 'badge bg-teal text-white',
                                    'rejected' => 'badge bg-danger',
                                    default => 'badge bg-warning text-dark',
                                };
                            @endphp
                            <span class="{{ $statusClass }}">
                                {{ $app->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-muted small">
                            {{ optional($app->submitted_at ?? $app->created_at)->format('d M Y') }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.applications.show', $app) }}" class="btn btn-sm btn-primary px-2 py-1">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted p-4">
                            No applications submitted yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

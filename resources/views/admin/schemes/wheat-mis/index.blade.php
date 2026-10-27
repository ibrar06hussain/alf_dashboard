@extends('layouts.admin')

@section('title', 'Wheat Seed (50:50 Cost Sharing) MIS Dashboard')

@push('styles')
<style>
    .mis-kpi-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .mis-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .mis-kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .mis-kpi-icon--emerald { background: #ecfdf5; color: #059669; }
    .mis-kpi-icon--amber   { background: #fffbeb; color: #d97706; }
    .mis-kpi-icon--blue    { background: #eff6ff; color: #2563eb; }
    .mis-kpi-icon--indigo  { background: #eef2ff; color: #4f46e5; }
    .mis-kpi-icon--rose    { background: #fff1f2; color: #e11d48; }
    .mis-kpi-icon--teal    { background: #f0fdfa; color: #0d9488; }
    .mis-kpi-icon--purple  { background: #faf5ff; color: #7e22ce; }

    .mis-badge-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1;
    }
    .mis-badge-surplus  { background: #dcfce7; color: #166534; }
    .mis-badge-deficit  { background: #fee2e2; color: #991b1b; }
    .mis-badge-balanced { background: #e0f2fe; color: #075985; }
    .mis-badge-unutilized { background: #f1f5f9; color: #475569; }

    .tree-node {
        border-left: 2px solid #e2e8f0;
        margin-left: 1rem;
        padding-left: 0.75rem;
    }
    .data-dictionary-table th {
        background-color: #f8fafc;
        font-weight: 600;
        color: #334155;
    }
    .gis-stat-badge {
        font-size: 0.8rem;
        padding: 4px 8px;
        border-radius: 6px;
    }
</style>
@endpush

@section('content')

@php
    $exportParams = array_merge($filters, $maskPii ? [] : ['unmask' => 1]);
@endphp

@include('admin.partials.page-hero', [
    'title' => 'Distribution of Wheat Seed (50:50 Cost Sharing) — Scheme MIS',
    'subtitle' => 'Scheme-specific executive dashboard for seed demand, allocations, land analytics, logistics, and cost-sharing.',
    'icon' => 'fa-solid fa-wheat-awn',
    'actions' => [
        ['label' => 'Excel', 'url' => route('admin.wheat-mis.export', array_merge($exportParams, ['format' => 'xlsx'])), 'class' => 'btn btn-sm btn-success'],
        ['label' => 'CSV', 'url' => route('admin.wheat-mis.export', array_merge($exportParams, ['format' => 'csv'])), 'class' => 'btn btn-sm btn-info text-white'],
        ['label' => 'PDF Report', 'url' => route('admin.wheat-mis.export', array_merge($exportParams, ['format' => 'pdf'])), 'class' => 'btn btn-sm btn-danger'],
    ],
])

{{-- ACTION BAR: CONFIGURATION & DATA DICTIONARY BUTTONS --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-primary px-3 py-2 fs-7">
            <i class="fa-solid fa-certificate me-1"></i> Scheme ID: {{ \App\Services\WheatSeedMisService::SCHEME_ID }}
        </span>
        <span class="badge bg-secondary px-3 py-2 fs-7">
            <i class="fa-solid fa-file-signature me-1"></i> Form ID: {{ \App\Services\WheatSeedMisService::FORM_ID }}
        </span>
        @if($configuredRate !== null)
            <span class="badge bg-success px-3 py-2 fs-7">
                <i class="fa-solid fa-tag me-1"></i> Base Rate: PKR {{ number_format($configuredRate, 2) }} / {{ $seedSettings['bag_weight_kg'] ?? 50 }}kg bag
            </span>
            @if(!empty($kpis['cost_sharing']['is_district_varying']))
                <span class="badge bg-info text-white px-3 py-2 fs-7" title="Delivered seed rates vary across districts based on transportation charges">
                    <i class="fa-solid fa-truck-fast me-1"></i> Delivered Rates: PKR {{ number_format($kpis['cost_sharing']['rate_min'], 0) }} – {{ number_format($kpis['cost_sharing']['rate_max'], 0) }} / bag (Transport Adjusted)
                </span>
            @endif
        @else
            <span class="badge bg-warning text-dark px-3 py-2 fs-7">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> Approved Bag Rate: Not yet configured
            </span>
        @endif
        <span class="badge bg-light text-dark border px-3 py-2 fs-7">
            <i class="fa-solid fa-scale-balanced me-1 text-primary"></i> Cost Share: {{ $seedSettings['govt_share_pct'] ?? 50 }}% Gov / {{ $seedSettings['farmer_share_pct'] ?? 50 }}% Farmer
        </span>
        <span class="badge bg-light text-dark border px-3 py-2 fs-7">
            <i class="fa-solid fa-seedling me-1 text-success"></i> Sowing Baseline: {{ $seedSettings['kanals_per_bag'] ?? 4 }} Kanals/Bag
        </span>
        @if(!empty($seedSettings['variety_name']))
            <span class="badge bg-light text-dark border px-3 py-2 fs-7 d-none d-md-inline-block">
                <i class="fa-solid fa-wheat-awn me-1 text-warning"></i> {{ $seedSettings['variety_name'] }}
            </span>
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2">
        @if(auth()->user()->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture']))
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rateConfigModal" data-toggle="modal" data-target="#rateConfigModal" id="btn-configure-seed-data">
                <i class="fa-solid fa-seedling me-1"></i> Configure Seed Data
            </button>
        @endif
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#dataDictionaryModal" data-toggle="modal" data-target="#dataDictionaryModal" id="btn-open-data-dictionary">
            <i class="fa-solid fa-book-bookmark me-1"></i> Data Dictionary
        </button>
    </div>
</div>

{{-- COMPREHENSIVE FILTER PANEL --}}
<div class="card alf-panel mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-filter me-2 text-primary"></i>Filter Scheme Applications & Analytics</span>
        <a href="{{ route('admin.wheat-mis.index') }}" class="btn btn-link btn-sm text-decoration-none p-0">Reset Filters</a>
    </div>
    <div class="card-body">
        <form id="wheat-mis-filters" method="GET" action="{{ route('admin.wheat-mis.index') }}" class="row g-2">
            {{-- Division --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Division</label>
                <select name="division" class="form-select form-select-sm">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $div)
                        <option value="{{ $div }}" @selected(($filters['division'] ?? '') === $div)>{{ $div }}</option>
                    @endforeach
                </select>
            </div>

            {{-- District --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">District</label>
                <select name="district_id" class="form-select form-select-sm">
                    <option value="">All Districts</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected(($filters['district_id'] ?? null) == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tehsil --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Tehsil</label>
                <input type="text" name="tehsil" class="form-control form-control-sm" placeholder="e.g. Gilgit" value="{{ $filters['tehsil'] ?? '' }}">
            </div>

            {{-- Union Council --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Union Council</label>
                <input type="text" name="union_council" class="form-control form-control-sm" placeholder="e.g. Hanzal Kargah" value="{{ $filters['union_council'] ?? '' }}">
            </div>

            {{-- Village --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Village</label>
                <input type="text" name="village" class="form-control form-control-sm" placeholder="e.g. Oshikhandass" value="{{ $filters['village'] ?? '' }}">
            </div>

            {{-- Decision / Status --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Decision / Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Decisions</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Survey Status --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Survey Status</label>
                <select name="survey_status" class="form-select form-select-sm">
                    <option value="">All Survey States</option>
                    <option value="verified" @selected(($filters['survey_status'] ?? '') === 'verified')>Verified / Surveyed</option>
                    <option value="pending_survey" @selected(($filters['survey_status'] ?? '') === 'pending_survey')>Pending Field Survey</option>
                </select>
            </div>

            {{-- Reviewing Officer --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">Reviewing Officer</label>
                <select name="officer_id" class="form-select form-select-sm">
                    <option value="">All Officers</option>
                    @foreach($officers as $officer)
                        <option value="{{ $officer->id }}" @selected(($filters['officer_id'] ?? null) == $officer->id)>{{ $officer->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- From Date --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $filters['from_date'] ?? '' }}">
            </div>

            {{-- To Date --}}
            <div class="col-md-3 col-lg-2">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $filters['to_date'] ?? '' }}">
            </div>

            {{-- Keyword Search --}}
            <div class="col-md-6 col-lg-3">
                <label class="form-label small text-muted mb-1">Search Keyword</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="App No, CNIC, Name, Phone..." value="{{ $filters['q'] ?? '' }}">
            </div>

            {{-- Submit / PII Toggle --}}
            <div class="col-md-6 col-lg-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Apply
                </button>
                @if($canUnmask)
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="unmask" value="1" id="unmaskToggle" @checked(! $maskPii) onchange="this.form.submit()">
                        <label class="form-check-label small" for="unmaskToggle" title="Authorized PII Unmasking">Show Full PII</label>
                    </div>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- EXECUTIVE KPIS ROW 1: CORE APPLICATIONS & LAND METRICS --}}
<div class="row g-3 mb-3">
    {{-- Card 1: Applications --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Applications</div>
                    <div class="h2 mb-0 fw-bold text-dark" id="kpi-total-applications">{{ number_format($kpis['total_applications']) }}</div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--emerald"><i class="fa-solid fa-users-viewfinder"></i></div>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <span class="badge bg-warning text-dark" title="Pending">{{ $kpis['status_counts']['pending'] ?? 0 }} Pend</span>
                <span class="badge bg-info text-white" title="District Verified">{{ $kpis['status_counts']['district_verified'] ?? 0 }} Ver</span>
                <span class="badge bg-primary" title="Directorate Approved">{{ $kpis['status_counts']['directorate_approved'] ?? 0 }} Appr</span>
                <span class="badge bg-success" title="Disbursed">{{ $kpis['status_counts']['disbursed'] ?? 0 }} Disb</span>
                <span class="badge bg-danger" title="Rejected">{{ $kpis['status_counts']['rejected'] ?? 0 }} Rej</span>
            </div>
        </div>
    </div>

    {{-- Card 2: Available Land (Kanals & Acres) --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Available Wheat Land</div>
                    <div class="h2 mb-0 fw-bold text-dark" id="kpi-available-land">
                        {{ number_format($kpis['land']['available_kanals'], 1) }} <span class="fs-6 text-muted">Kanals</span>
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--teal"><i class="fa-solid fa-mountain-sun"></i></div>
            </div>
            <div class="small text-muted mt-2">
                <span class="fw-bold text-primary">{{ number_format($kpis['land']['available_acres'], 2) }}</span> Acres
                <span class="text-secondary">(1 Acre = 8 Kanals)</span>
            </div>
        </div>
    </div>

    {{-- Card 3: Previous Cultivation & Comparison --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Last Year Cultivation</div>
                    <div class="h3 mb-0 fw-bold text-dark" id="kpi-cultivated-land">
                        @if($kpis['land']['has_cultivated_data'])
                            {{ number_format($kpis['land']['cultivated_kanals_last_year'], 1) }} <span class="fs-6 text-muted">Kanals</span>
                        @else
                            <span class="badge bg-light text-secondary border">Not yet captured</span>
                        @endif
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--amber"><i class="fa-solid fa-clock-rotate-left"></i></div>
            </div>
            <div class="small text-muted mt-2">
                @if($kpis['land']['has_cultivated_data'])
                    Ratio Available/Cultivated: <strong>{{ $kpis['land']['availability_vs_previous_ratio'] ?? '—' }}x</strong>
                @else
                    <span class="text-muted">Awaiting field survey record capture</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Card 4: Demand-Supply Bag Summary --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Seed Bags (50kg Each)</div>
                    <div class="h2 mb-0 fw-bold text-dark" id="kpi-approved-bags">
                        {{ number_format($kpis['bags']['approved']) }} <span class="fs-6 text-muted">Approved</span>
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--indigo"><i class="fa-solid fa-boxes-stacked"></i></div>
            </div>
            <div class="d-flex justify-content-between align-items-center small text-muted mt-2">
                <span>Req: <strong>{{ number_format($kpis['bags']['requested']) }}</strong></span>
                <span>Quota: <strong>{{ number_format($kpis['bags']['allocated']) }}</strong></span>
                <span>Disb: <strong class="text-success">{{ number_format($kpis['bags']['disbursed']) }}</strong></span>
            </div>
        </div>
    </div>
</div>

{{-- EXECUTIVE KPIS ROW 2: WEIGHTS, LOGISTICS, 50:50 COST-SHARING & COVERAGE --}}
<div class="row g-3 mb-4">
    {{-- Card 5: Weight Equivalents (kg and MT) --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Weight Equivalents</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        {{ number_format($kpis['weights']['approved_mt'], 2) }} <span class="fs-6 text-muted">MT Approved</span>
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--purple"><i class="fa-solid fa-weight-hanging"></i></div>
            </div>
            <div class="small text-muted mt-2">
                <div>Approved: <strong>{{ number_format($kpis['weights']['approved_kg']) }} kg</strong></div>
                <div>Allocated Quota: <strong>{{ number_format($kpis['weights']['allocated_mt'], 2) }} MT</strong> ({{ number_format($kpis['weights']['allocated_kg']) }} kg)</div>
            </div>
        </div>
    </div>

    {{-- Card 6: Averages --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Agronomic Averages</div>
                    <div class="h3 mb-0 fw-bold text-dark">
                        {{ $kpis['averages']['requested_bags_per_applicant'] }} <span class="fs-6 text-muted">Bags / App</span>
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--blue"><i class="fa-solid fa-calculator"></i></div>
            </div>
            <div class="small text-muted mt-2">
                <div>Seed Rate: <strong>{{ $kpis['averages']['bags_per_kanal'] }}</strong> bags/kanal</div>
                <div>Avg Landholding: <strong>{{ $kpis['averages']['kanals_per_applicant'] }}</strong> kanals</div>
            </div>
        </div>
    </div>

    {{-- Card 7: 50:50 Cost-Sharing Scheme --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Cost-Sharing (50:50)</div>
                    @if($kpis['cost_sharing']['is_configured'])
                        <div class="h3 mb-0 fw-bold text-success">
                            PKR {{ number_format($kpis['cost_sharing']['total_approved_value']) }}
                        </div>
                    @else
                        <div class="h5 mb-0 fw-bold text-secondary">
                            <span class="badge bg-light text-secondary border">Not yet configured</span>
                        </div>
                    @endif
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--emerald"><i class="fa-solid fa-handshake"></i></div>
            </div>
            <div class="small text-muted mt-2">
                @if($kpis['cost_sharing']['is_configured'])
                    <div>Gov Share ({{ $seedSettings['govt_share_pct'] ?? 50 }}%): <strong class="text-primary">PKR {{ number_format($kpis['cost_sharing']['gov_share_50']) }}</strong></div>
                    <div>Farmer Share ({{ $seedSettings['farmer_share_pct'] ?? 50 }}%): <strong class="text-dark">PKR {{ number_format($kpis['cost_sharing']['farmer_share_50']) }}</strong></div>
                    @if(!empty($kpis['cost_sharing']['is_district_varying']))
                        <div class="text-muted small mt-1"><i class="fa-solid fa-truck text-muted me-1"></i>Transport Carriage: <strong>PKR {{ number_format($kpis['cost_sharing']['total_transport_cost']) }}</strong></div>
                    @endif
                @else
                    <span class="text-muted">Approved per-bag rate must be configured.</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Card 8: Village Coverage & Survey Pipeline --}}
    <div class="col-sm-6 col-xl-3">
        <div class="mis-kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Village Coverage</div>
                    <div class="h2 mb-0 fw-bold text-dark">
                        {{ number_format($kpis['village_coverage']['unique_villages']) }} <span class="fs-6 text-muted">Villages</span>
                    </div>
                </div>
                <div class="mis-kpi-icon mis-kpi-icon--rose"><i class="fa-solid fa-map-location-dot"></i></div>
            </div>
            <div class="small text-muted mt-2">
                <div>Survey Verified: <strong class="text-success">{{ $kpis['survey_status']['verified'] }}</strong> | Pending: <strong class="text-warning">{{ $kpis['survey_status']['pending'] }}</strong></div>
                <div>Avg Apps / Village: <strong>{{ $kpis['village_coverage']['avg_applications_per_village'] }}</strong></div>
            </div>
        </div>
    </div>
</div>

{{-- INTERACTIVE CHARTS ROW 1 --}}
<div class="row g-3 mb-4">
    {{-- Chart 1: District Demand vs Supply Gap --}}
    <div class="col-lg-8">
        <div class="card alf-panel h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-chart-column text-primary me-2"></i>District Demand-Supply Gap (Allocated Quota vs Approved Bags)</span>
                <span class="badge bg-light text-secondary border">50kg Bags</span>
            </div>
            <div class="card-body">
                <div id="chart-demand-supply" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>

    {{-- Chart 2: Application Decision & Lifecycle Pipeline --}}
    <div class="col-lg-4">
        <div class="card alf-panel h-100">
            <div class="card-header bg-light">
                <i class="fa-solid fa-chart-pie text-primary me-2"></i>Application Status Breakdown
            </div>
            <div class="card-body">
                <div id="chart-status-donut" style="min-height: 350px;"></div>
            </div>
        </div>
    </div>
</div>

{{-- INTERACTIVE CHARTS ROW 2 --}}
<div class="row g-3 mb-4">
    {{-- Chart 3: Landholding Size Distribution Bands --}}
    <div class="col-md-6 col-lg-4">
        <div class="card alf-panel h-100">
            <div class="card-header bg-light">
                <i class="fa-solid fa-layer-group text-primary me-2"></i>Landholding Size Distribution Bands
            </div>
            <div class="card-body">
                <div id="chart-land-bands" style="min-height: 280px;"></div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-info-circle me-1"></i> Categorized by available wheat land (Kanals).
                </div>
            </div>
        </div>
    </div>

    {{-- Chart 4: Water Availability --}}
    <div class="col-md-6 col-lg-4">
        <div class="card alf-panel h-100">
            <div class="card-header bg-light">
                <i class="fa-solid fa-droplet text-primary me-2"></i>Water Availability
            </div>
            <div class="card-body">
                <div id="chart-water-donut" style="min-height: 280px;"></div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-seedling me-1"></i> Confirms irrigation feasibility on farm land.
                </div>
            </div>
        </div>
    </div>

    {{-- Chart 5: Road Accessibility --}}
    <div class="col-md-12 col-lg-4">
        <div class="card alf-panel h-100">
            <div class="card-header bg-light">
                <i class="fa-solid fa-road text-primary me-2"></i>Road Access to Farm Land
            </div>
            <div class="card-body">
                <div id="chart-road-bar" style="min-height: 280px;"></div>
                <div class="small text-muted mt-2">
                    <i class="fa-solid fa-truck me-1"></i> Logistics feasibility for seed bag delivery.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- DISTRICT DEMAND-SUPPLY GAP MATRIX TABLE --}}
<div class="card alf-panel mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-table-list text-primary me-2"></i>District Demand-Supply & Quota Matrix</span>
        <span class="small text-muted">Supply from Scheme Quota (500 Bags Total across GB)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Division</th>
                    <th>District</th>
                    <th class="text-end">Quota (Supply)</th>
                    <th class="text-end">Requested (Demand)</th>
                    <th class="text-end">Approved</th>
                    <th class="text-end">Disbursed</th>
                    <th class="text-end">Net Gap</th>
                    <th class="text-end">Available Land</th>
                    <th class="text-end">Approved (MT)</th>
                    <th class="text-end">Delivered Rate</th>
                    <th class="text-end">Total Valuation</th>
                    <th class="text-end">Gov Subsidy</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($demandSupplyGap as $row)
                    @php
                        $badgeClass = match($row['status_badge']) {
                            'Deficit' => 'mis-badge-deficit',
                            'Surplus Available' => 'mis-badge-surplus',
                            'Balanced' => 'mis-badge-balanced',
                            default => 'mis-badge-unutilized',
                        };
                    @endphp
                    <tr>
                        <td class="fw-semibold text-secondary">{{ $row['division'] }}</td>
                        <td class="fw-bold text-dark">{{ $row['district_name'] }}</td>
                        <td class="text-end fw-semibold">{{ number_format($row['quota']) }}</td>
                        <td class="text-end">{{ number_format($row['requested_bags']) }}</td>
                        <td class="text-end fw-bold text-primary">{{ number_format($row['approved_bags']) }}</td>
                        <td class="text-end text-success">{{ number_format($row['disbursed_bags']) }}</td>
                        <td class="text-end fw-bold {{ $row['gap'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $row['gap'] > 0 ? '+' : '' }}{{ number_format($row['gap']) }}
                        </td>
                        <td class="text-end">{{ number_format($row['available_kanals'], 1) }} k ({{ number_format($row['available_acres'], 2) }} ac)</td>
                        <td class="text-end">{{ number_format($row['approved_mt'], 2) }}</td>
                        <td class="text-end">
                            @if($row['rate_per_bag'] !== null)
                                <span class="fw-semibold text-dark">PKR {{ number_format($row['rate_per_bag'], 0) }}</span>
                                @if($row['transport_cost'] > 0)
                                    <span class="badge bg-light text-muted border d-block small py-0 px-1" title="Includes PKR {{ number_format($row['transport_cost'], 0) }} transport carriage" style="font-size: 0.68rem;">+{{ number_format($row['transport_cost'], 0) }} trans</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end fw-bold {{ $row['approved_cost'] > 0 ? 'text-dark' : 'text-muted' }}">
                            {{ $row['approved_cost'] > 0 ? 'PKR ' . number_format($row['approved_cost'], 0) : '—' }}
                        </td>
                        <td class="text-end fw-semibold {{ $row['gov_subsidy'] > 0 ? 'text-success' : 'text-muted' }}">
                            {{ $row['gov_subsidy'] > 0 ? 'PKR ' . number_format($row['gov_subsidy'], 0) : '—' }}
                        </td>
                        <td class="text-center">
                            <span class="mis-badge-pill {{ $badgeClass }}">{{ $row['status_badge'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2">Total GB Consolidated</td>
                    <td class="text-end">{{ number_format($demandSupplyGap->sum('quota')) }}</td>
                    <td class="text-end">{{ number_format($demandSupplyGap->sum('requested_bags')) }}</td>
                    <td class="text-end text-primary">{{ number_format($demandSupplyGap->sum('approved_bags')) }}</td>
                    <td class="text-end text-success">{{ number_format($demandSupplyGap->sum('disbursed_bags')) }}</td>
                    <td class="text-end {{ ($demandSupplyGap->sum('quota') - $demandSupplyGap->sum('approved_bags')) < 0 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($demandSupplyGap->sum('quota') - $demandSupplyGap->sum('approved_bags')) }}
                    </td>
                    <td class="text-end">{{ number_format($demandSupplyGap->sum('available_kanals'), 1) }} k</td>
                    <td class="text-end">{{ number_format($demandSupplyGap->sum('approved_mt'), 2) }}</td>
                    <td class="text-end text-muted">
                        @if($kpis['cost_sharing']['is_district_varying'] ?? false)
                            PKR {{ number_format($kpis['cost_sharing']['rate_min'], 0) }} – {{ number_format($kpis['cost_sharing']['rate_max'], 0) }}
                        @elseif($configuredRate !== null)
                            PKR {{ number_format($configuredRate, 0) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end text-dark">
                        {{ $demandSupplyGap->sum('approved_cost') > 0 ? 'PKR ' . number_format($demandSupplyGap->sum('approved_cost'), 0) : '—' }}
                    </td>
                    <td class="text-end text-success">
                        {{ $demandSupplyGap->sum('gov_subsidy') > 0 ? 'PKR ' . number_format($demandSupplyGap->sum('gov_subsidy'), 0) : '—' }}
                    </td>
                    <td class="text-center">—</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ADMINISTRATIVE ROLLUP: DIVISION -> DISTRICT -> TEHSIL -> UC -> VILLAGE --}}
<div class="card alf-panel mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-sitemap text-primary me-2"></i>Administrative Rollup (Division → District → Tehsil → UC → Village)</span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#adminRollupCollapse">
            Toggle Rollup
        </button>
    </div>
    <div class="collapse show" id="adminRollupCollapse">
        <div class="card-body">
            @if(empty($divisionRollup))
                <div class="text-muted small">No applications matching current filters.</div>
            @else
                <div class="accordion" id="divisionAccordion">
                    @foreach($divisionRollup as $divName => $divData)
                        @php $divId = 'div-' . \Illuminate\Support\Str::slug($divName); @endphp
                        <div class="accordion-item mb-2 border rounded">
                            <h2 class="accordion-header" id="heading-{{ $divId }}">
                                <button class="accordion-button collapsed py-2 bg-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $divId }}">
                                    <span class="me-auto text-dark">{{ $divName }}</span>
                                    <span class="badge bg-primary me-2">{{ $divData['total'] }} Applications</span>
                                    <span class="badge bg-secondary me-2">{{ number_format($divData['kanals'], 1) }} Kanals</span>
                                    <span class="badge bg-info text-white">{{ number_format($divData['bags']) }} Bags</span>
                                </button>
                            </h2>
                            <div id="collapse-{{ $divId }}" class="accordion-collapse collapse" data-bs-parent="#divisionAccordion">
                                <div class="accordion-body p-3">
                                    @foreach($divData['districts'] as $distName => $distData)
                                        <div class="mb-3 ps-2 border-start border-3 border-primary">
                                            <div class="d-flex justify-content-between align-items-center fw-semibold text-dark">
                                                <span><i class="fa-solid fa-location-dot me-1 text-danger"></i> {{ $distName }} District</span>
                                                <span class="small text-muted">{{ $distData['total'] }} apps · {{ number_format($distData['kanals'], 1) }} kanals · {{ $distData['bags'] }} bags</span>
                                            </div>

                                            @foreach($distData['tehsils'] as $tehName => $tehData)
                                                <div class="tree-node mt-2">
                                                    <div class="d-flex justify-content-between small text-secondary">
                                                        <span><i class="fa-solid fa-city me-1"></i> {{ $tehName }}</span>
                                                        <span>{{ $tehData['total'] }} apps · {{ number_format($tehData['kanals'], 1) }} k · {{ $tehData['bags'] }} bags</span>
                                                    </div>

                                                    @foreach($tehData['ucs'] as $ucName => $ucData)
                                                        <div class="tree-node mt-1">
                                                            <div class="d-flex justify-content-between small text-muted">
                                                                <span><i class="fa-regular fa-building me-1"></i> UC: {{ $ucName }}</span>
                                                                <span>{{ $ucData['total'] }} apps · {{ number_format($ucData['kanals'], 1) }} k · {{ $ucData['bags'] }} bags</span>
                                                            </div>

                                                            @foreach($ucData['villages'] as $vilName => $vilData)
                                                                <div class="tree-node mt-1 small text-muted" style="font-size: 0.82rem;">
                                                                    <i class="fa-solid fa-house-chimney me-1 text-success"></i> Village: {{ $vilName }}
                                                                    <span class="float-end">{{ $vilData['total'] }} apps ({{ number_format($vilData['kanals'], 1) }} k, {{ $vilData['bags'] }} bags)</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

{{-- GIS & FARM LOCATION STATUS SECTION --}}
<div class="card alf-panel mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-satellite text-primary me-2"></i>GIS Farm Location & GPS Spatial Verification</span>
        <span class="gis-stat-badge {{ $gisData['is_captured'] ? 'bg-success text-white' : 'bg-light text-secondary border' }}">
            {{ $gisData['status_note'] }}
        </span>
    </div>
    <div class="card-body">
        @if(! $gisData['is_captured'])
            <div class="alert alert-light border d-flex align-items-center mb-3">
                <i class="fa-solid fa-circle-info fs-4 text-secondary me-3"></i>
                <div>
                    <strong>GPS Coordinates: Not yet captured</strong>
                    <div class="small text-muted">The public application form captures farm textual descriptions (e.g. land location). Precise latitude/longitude coordinates require field survey verification by District Agriculture Officers before pinpoint geocoding can be rendered.</div>
                </div>
            </div>
        @endif

        {{-- GB Administrative Centroids Visualization --}}
        <div class="text-muted small mb-2 fw-semibold">District Hubs & Agricultural Extent (Gilgit-Baltistan):</div>
        <div class="row g-2">
            @foreach($gisData['district_centroids'] as $dist => $coords)
                <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                    <div class="p-2 border rounded bg-white text-center">
                        <div class="fw-bold text-dark small">{{ $dist }}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            {{ $coords['lat'] }}° N, {{ $coords['lng'] }}° E
                        </div>
                        <div class="badge bg-light text-secondary border mt-1" style="font-size: 0.7rem;">
                            {{ $kpis['village_coverage']['unique_villages'] > 0 ? 'Active Region' : 'No Submissions' }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- BENEFICIARY REGISTRY & FIELD AUDIT TABLE WITH AJAX FILTERING, FLEXIBLE PER-PAGE, AND DIRECT EXPORTS --}}
<div class="card alf-panel mb-4 shadow-sm" id="wheat-beneficiaries-card">
    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-id-card text-primary fs-5"></i>
            <div>
                <span class="fw-bold text-dark">Wheat Seed Beneficiary Registry</span>
                <span class="badge bg-primary text-white ms-2" id="grid-total-badge">{{ number_format($beneficiaries->total()) }} matching records</span>
                <span class="spinner-border spinner-border-sm text-primary ms-2 d-none" id="grid-loading-spinner" role="status" aria-hidden="true" title="Loading matching records..."></span>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted d-none d-md-inline me-1">Export Grid:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Export Beneficiaries">
                <a href="{{ route('admin.wheat-mis.export', array_merge($filters, ['format' => 'xlsx'])) }}" class="btn btn-outline-success grid-export-btn" id="grid-export-excel" title="Download Excel (.xlsx)">
                    <i class="fa-solid fa-file-excel me-1"></i>Excel
                </a>
                <a href="{{ route('admin.wheat-mis.export', array_merge($filters, ['format' => 'csv'])) }}" class="btn btn-outline-info grid-export-btn" id="grid-export-csv" title="Download CSV (.csv)">
                    <i class="fa-solid fa-file-csv me-1"></i>CSV
                </a>
                <a href="{{ route('admin.wheat-mis.export', array_merge($filters, ['format' => 'pdf'])) }}" class="btn btn-outline-danger grid-export-btn" id="grid-export-pdf" title="Download PDF Report (.pdf)">
                    <i class="fa-solid fa-file-pdf me-1"></i>PDF
                </a>
            </div>
        </div>
    </div>

    {{-- DEDICATED INLINE AJAX FILTER TOOLBAR --}}
    <div class="card-body bg-light-subtle border-bottom p-3">
        <form id="beneficiary-grid-filter-form" class="row g-2 align-items-end" onsubmit="return false;">
            {{-- District --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-map-location-dot text-primary me-1"></i>District
                </label>
                <select name="district_id" class="form-select form-select-sm grid-filter-input" id="grid-district-id">
                    <option value="">All Districts</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected(($filters['district_id'] ?? null) == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tehsil --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-location-dot text-info me-1"></i>Tehsil
                </label>
                <input type="text" name="tehsil" class="form-control form-control-sm grid-filter-input" id="grid-tehsil" placeholder="e.g. Gilgit / Danyore" value="{{ $filters['tehsil'] ?? '' }}">
            </div>

            {{-- Available Land (Min Kanals) --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-chart-area text-success me-1"></i>Min Land (Kanals)
                </label>
                <input type="number" step="0.5" min="0" name="min_land" class="form-control form-control-sm grid-filter-input" id="grid-min-land" placeholder="Min Kanals" value="{{ $filters['min_land'] ?? '' }}">
            </div>

            {{-- Bags Required (Min Bags) --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-wheat-awn text-warning me-1"></i>Min Bags (50kg)
                </label>
                <input type="number" min="1" name="min_bags" class="form-control form-control-sm grid-filter-input" id="grid-min-bags" placeholder="Min Bags" value="{{ $filters['min_bags'] ?? '' }}">
            </div>

            {{-- Search Keyword (App No, Name, CNIC, Phone) --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-magnifying-glass text-secondary me-1"></i>Search Keyword
                </label>
                <input type="text" name="q" class="form-control form-control-sm grid-filter-input" id="grid-search-q" placeholder="App No, CNIC, Name..." value="{{ $filters['q'] ?? '' }}">
            </div>

            {{-- Records Per Page (25, 50, 100, 500, all) --}}
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted mb-1">
                    <i class="fa-solid fa-list-ol text-dark me-1"></i>Show Records
                </label>
                <div class="d-flex gap-1">
                    <select name="per_page" class="form-select form-select-sm grid-filter-input flex-grow-1" id="grid-per-page">
                        <option value="25" @selected(($perPage ?? 25) == 25)>25 per page</option>
                        <option value="50" @selected(($perPage ?? 25) == 50)>50 per page</option>
                        <option value="100" @selected(($perPage ?? 25) == 100)>100 per page</option>
                        <option value="500" @selected(($perPage ?? 25) == 500)>500 per page</option>
                        <option value="all" @selected(($perPage ?? 25) === 'all')>Show All Records</option>
                    </select>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-grid-filter-reset" title="Reset Grid Filters">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- TABLE AND PAGINATION DYNAMIC CONTAINER --}}
    <div id="beneficiary-grid-container" class="position-relative">
        @include('admin.schemes.wheat-mis.partials.beneficiary-grid')
    </div>
</div>

@endsection

@push('modals')
{{-- MODAL: CONFIGURE SEED DATA & RATE (FOR AUTHORIZED EXECUTIVES) --}}
@if(auth()->user()->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture']))
<div class="modal fade" id="rateConfigModal" tabindex="-1" aria-labelledby="rateConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('admin.wheat-mis.rate') }}" class="modal-content shadow-lg">
            @csrf
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="rateConfigModalLabel">
                    <i class="fa-solid fa-seedling text-primary me-2"></i>Configure Scheme Seed Data & Approved Rate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border small mb-3">
                    <i class="fa-solid fa-circle-info text-primary me-1"></i>
                    Configure official government parameters for the <strong>Distribution of Wheat Seed</strong> scheme. These settings drive automated cost-sharing, financial KPIs, weight conversions, and derived land allocations.
                </div>

                <div class="row g-3">
                    {{-- Approved Bag Rate --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Approved Rate per Standard Bag (PKR) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">PKR</span>
                            <input type="number" step="0.01" min="0" max="500000" id="input_wheat_seed_bag_rate" name="wheat_seed_bag_rate" class="form-control" placeholder="e.g. 6500" value="{{ $configuredRate ?? '' }}">
                        </div>
                        <div class="form-text small text-muted">Leave empty if not yet determined. Never fabricate prices.</div>
                    </div>

                    {{-- Standard Bag Weight --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Standard Packaging Weight (kg) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.5" min="1" max="1000" id="input_wheat_seed_bag_weight_kg" name="wheat_seed_bag_weight_kg" class="form-control" placeholder="50" value="{{ $seedSettings['bag_weight_kg'] ?? 50 }}">
                            <span class="input-group-text bg-white">kg / bag</span>
                        </div>
                        <div class="form-text small text-muted">Standard bag weight for metric ton conversions (default: 50 kg).</div>
                    </div>

                    {{-- Government Cost-Share % --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Government Cost-Share (%) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="1" min="0" max="100" id="input_wheat_seed_govt_share_pct" name="wheat_seed_govt_share_pct" class="form-control" placeholder="50" value="{{ $seedSettings['govt_share_pct'] ?? 50 }}">
                            <span class="input-group-text bg-white">%</span>
                        </div>
                        <div class="form-text small text-muted">Subsidy portion funded by Government of GB (default: 50%).</div>
                    </div>

                    {{-- Farmer Contribution % --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Farmer Contribution (%) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="1" min="0" max="100" id="input_wheat_seed_farmer_share_pct" name="wheat_seed_farmer_share_pct" class="form-control" placeholder="50" value="{{ $seedSettings['farmer_share_pct'] ?? 50 }}">
                            <span class="input-group-text bg-white">%</span>
                        </div>
                        <div class="form-text small text-muted">Matching portion contributed by farmer (default: 50%).</div>
                    </div>

                    {{-- Baseline Agronomic Sowing Rate --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Agronomic Sowing Rate (Kanals / Bag) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.1" min="0.1" max="100" id="input_wheat_seed_kanals_per_bag" name="wheat_seed_kanals_per_bag" class="form-control" placeholder="4" value="{{ $seedSettings['kanals_per_bag'] ?? 4 }}">
                            <span class="input-group-text bg-white">Kanals / Bag</span>
                        </div>
                        <div class="form-text small text-muted">Baseline sowing requirement (4 Kanals/Bag = 12.5 kg seed/kanal).</div>
                    </div>

                    {{-- Target Season / Fiscal Year --}}
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Scheme Fiscal Year / Cycle
                        </label>
                        <input type="text" name="wheat_seed_fiscal_year" class="form-control" placeholder="e.g. Rabi 2025-2026" value="{{ $seedSettings['fiscal_year'] ?? 'Rabi 2025-2026' }}">
                        <div class="form-text small text-muted">Agricultural season or budgetary cycle.</div>
                    </div>

                    {{-- Certified Seed Variety / Specification --}}
                    <div class="col-12">
                        <label class="form-label fw-bold small text-dark mb-1">
                            Certified Seed Variety & Procurement Specs
                        </label>
                        <input type="text" name="wheat_seed_variety_name" class="form-control" placeholder="e.g. Certified Wheat Seed (Faisalabad-08 / Akbar-19)" value="{{ $seedSettings['variety_name'] ?? 'Certified Wheat Seed' }}">
                        <div class="form-text small text-muted">Official seed tag, variety, or procurement lot descriptor.</div>
                    </div>

                    {{-- District-Wise Transportation Charges & Delivered Seed Rates --}}
                    <div class="col-12">
                        <div class="card border border-primary border-opacity-25 rounded-3 mb-2 shadow-sm">
                            <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark small">
                                    <i class="fa-solid fa-truck-ramp-box text-primary me-1"></i> District-Wise Transportation Charges & Delivered Seed Rates
                                </span>
                                <span class="badge bg-primary text-white" style="font-size: 0.72rem;">
                                    Distance & Terrain Carriage Surcharges
                                </span>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <p class="small text-muted mb-2">
                                    Seed cost per district may differ due to distinct transportation charges from central depots across Gilgit-Baltistan terrains. Setting a transport surcharge updates that district's delivered rate (Base Rate + Transport). You can also directly type a delivered rate.
                                </p>
                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0" id="table-district-rates-form">
                                        <thead class="table-light sticky-top small" style="z-index: 1;">
                                            <tr>
                                                <th>Division & District</th>
                                                <th class="text-end" style="min-width: 100px;">Base Rate</th>
                                                <th style="min-width: 140px;">Transport Surcharge (PKR)</th>
                                                <th style="min-width: 150px;">Delivered Bag Rate (PKR)</th>
                                                <th class="text-end" style="min-width: 100px;">Gov Share</th>
                                                <th class="text-end" style="min-width: 100px;">Farmer Share</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            @foreach($districtRatesTable as $dist)
                                                <tr class="district-rate-row" data-district-id="{{ $dist->district_id }}">
                                                    <td>
                                                        <div class="fw-bold text-dark">{{ $dist->district_name }}</div>
                                                        <span class="badge bg-light text-muted border py-0 px-1" style="font-size: 0.7rem;">{{ $dist->division }}</span>
                                                    </td>
                                                    <td class="text-end text-muted">
                                                        <span class="preview-base-rate">PKR {{ $configuredRate !== null ? number_format($configuredRate, 0) : '—' }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light text-muted py-0 px-1">+</span>
                                                            <input type="number" 
                                                                   step="1" 
                                                                   min="0" 
                                                                   max="100000" 
                                                                   name="district_transport[{{ $dist->district_id }}]" 
                                                                   class="form-control form-control-sm input-district-transport" 
                                                                   data-district-id="{{ $dist->district_id }}" 
                                                                   placeholder="0" 
                                                                   value="{{ $dist->transport_cost > 0 ? $dist->transport_cost : '' }}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-white py-0 px-1">PKR</span>
                                                            <input type="number" 
                                                                   step="0.01" 
                                                                   min="0" 
                                                                   max="500000" 
                                                                   name="district_rate[{{ $dist->district_id }}]" 
                                                                   class="form-control form-control-sm input-district-rate fw-bold text-dark" 
                                                                   data-district-id="{{ $dist->district_id }}" 
                                                                   placeholder="{{ $configuredRate ? ($configuredRate + $dist->transport_cost) : '' }}" 
                                                                   value="{{ $dist->delivered_rate !== null ? $dist->delivered_rate : '' }}">
                                                        </div>
                                                    </td>
                                                    <td class="text-end fw-semibold text-success">
                                                        <span class="dist-preview-gov" data-district-id="{{ $dist->district_id }}">
                                                            {{ $dist->gov_share !== null ? 'PKR ' . number_format($dist->gov_share, 0) : '—' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-semibold text-primary">
                                                        <span class="dist-preview-farmer" data-district-id="{{ $dist->district_id }}">
                                                            {{ $dist->farmer_share !== null ? 'PKR ' . number_format($dist->farmer_share, 0) : '—' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Live Calculation Breakdown Card --}}
                    <div class="col-12">
                        <div class="p-3 rounded bg-light border" id="seed-live-calc-box">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold small text-dark">
                                    <i class="fa-solid fa-calculator text-primary me-1"></i> Cost-Sharing Live Breakdown (Base Standard Bag)
                                </span>
                                <span class="badge bg-primary text-white" id="seed-calc-badge">50:50 Cost-Sharing</span>
                            </div>
                            <div class="row g-2 text-center" id="seed-calc-preview-row">
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded border">
                                        <div class="text-muted small">Base Bag Rate</div>
                                        <div class="fw-bold text-dark fs-6" id="calc-preview-rate">PKR {{ $configuredRate ? number_format($configuredRate, 2) : '—' }}</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded border">
                                        <div class="text-muted small">Gov Share (<span id="calc-preview-gov-pct">{{ $seedSettings['govt_share_pct'] ?? 50 }}</span>%)</div>
                                        <div class="fw-bold text-success fs-6" id="calc-preview-gov">PKR {{ $configuredRate ? number_format($configuredRate * (($seedSettings['govt_share_pct'] ?? 50) / 100), 2) : '—' }}</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white rounded border">
                                        <div class="text-muted small">Farmer Share (<span id="calc-preview-farmer-pct">{{ $seedSettings['farmer_share_pct'] ?? 50 }}</span>%)</div>
                                        <div class="fw-bold text-primary fs-6" id="calc-preview-farmer">PKR {{ $configuredRate ? number_format($configuredRate * (($seedSettings['farmer_share_pct'] ?? 50) / 100), 2) : '—' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Security Password Authorization Requirement --}}
                    <div class="col-12 mt-2">
                        <div class="card border border-danger border-opacity-50 bg-danger-subtle rounded-3 p-3 shadow-none">
                            <div class="d-flex align-items-center mb-1 text-danger">
                                <i class="fa-solid fa-shield-halved fs-5 me-2"></i>
                                <span class="fw-bold">Security Password Verification Required</span>
                            </div>
                            <p class="small text-muted mb-2">
                                Changes to seed data configuration and district delivery rates directly impact budget subsidies and beneficiary challan fees. Please enter your account login password to authorize this modification.
                            </p>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold small text-dark mb-1">
                                        Your Login Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-muted"></i></span>
                                        <input type="password" 
                                               name="password" 
                                               id="input_config_password" 
                                               class="form-control" 
                                               placeholder="Enter your account password..." 
                                               required 
                                               autocomplete="current-password">
                                    </div>
                                    @error('password')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-5">
                                    <span class="small text-muted d-block mt-md-4">
                                        <i class="fa-solid fa-circle-check text-success me-1"></i> Identity verification required per ALFGB security policy.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Seed Configuration
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- MODAL: COMPREHENSIVE DATA DICTIONARY --}}
<div class="modal fade" id="dataDictionaryModal" tabindex="-1" aria-labelledby="dataDictionaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="dataDictionaryModalLabel">
                        <i class="fa-solid fa-book-bookmark text-primary me-2"></i>Wheat Seed Scheme MIS — Data Dictionary & KPI Formulas
                    </h5>
                    <div class="small text-muted">Complete mapping of public form fields, database storage keys, normalization rules, and agronomic derivations.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                {{-- Instant Search Toolbar --}}
                <div class="p-3 bg-light border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="dict-search-input" class="form-control" placeholder="Search dictionary (e.g. land, bags, cnic, quota, rate)...">
                        </div>
                    </div>
                    <span class="badge bg-secondary" id="dict-counter-badge">{{ count($dataDictionary) }} Dictionary Entries</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover data-dictionary-table mb-0 small">
                        <thead>
                            <tr>
                                <th style="min-width: 160px;">Form Question / Parameter</th>
                                <th style="min-width: 140px;">Field Key / ID</th>
                                <th>Database Table</th>
                                <th>Column / Path</th>
                                <th>Data Type</th>
                                <th style="min-width: 200px;">Normalization & Storage</th>
                                <th style="min-width: 220px;">KPI & Analytics Usage</th>
                            </tr>
                        </thead>
                        <tbody id="dict-table-body">
                            @foreach($dataDictionary as $item)
                                <tr class="dict-row">
                                    <td class="fw-bold text-dark dict-searchable">{{ $item['form_question'] }}</td>
                                    <td class="dict-searchable"><code>{{ $item['field_key'] }}</code> <span class="text-muted d-block small">({{ is_numeric($item['field_id']) ? 'ID: ' . $item['field_id'] : $item['field_id'] }})</span></td>
                                    <td class="dict-searchable"><span class="badge bg-light text-dark border">{{ $item['target_table'] }}</span></td>
                                    <td class="dict-searchable"><code>{{ $item['target_column'] }}</code></td>
                                    <td class="dict-searchable"><span class="badge bg-secondary text-white">{{ $item['data_type'] }}</span></td>
                                    <td class="dict-searchable text-muted">{{ $item['normalization'] }}</td>
                                    <td class="dict-searchable text-primary fw-medium">{{ $item['kpi_usage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-light border-top">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-square-root-variable text-primary me-1"></i> Core Agronomic & Financial KPI Formulas:</h6>
                    <div class="row g-2 small text-muted">
                        <div class="col-md-6">
                            <ul class="mb-0 ps-3">
                                <li><strong>Available Land (Acres):</strong> <code>Acres = Kanals / 8</code> (1 Acre = 8 Kanals).</li>
                                <li><strong>Seed Requirement (Bags):</strong> From field <code>bags_required</code> if submitted, or standard agronomic baseline: <code>Bags = ceil(Kanals / {{ $seedSettings['kanals_per_bag'] ?? 4 }})</code>.</li>
                                <li><strong>Packaging Weight (kg):</strong> <code>Weight (kg) = Bags * {{ $seedSettings['bag_weight_kg'] ?? 50 }} kg</code>.</li>
                                <li><strong>Metric Tons (MT):</strong> <code>Weight (MT) = Weight (kg) / 1000 = (Bags * {{ $seedSettings['bag_weight_kg'] ?? 50 }}) / 1000</code>.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0 ps-3">
                                <li><strong>Demand-Supply Net Gap:</strong> <code>Net Gap = Allocated Quota - Approved Bags</code>.</li>
                                <li><strong>District Delivered Rate:</strong> <code>Delivered Rate = Base Rate + Transport Carriage (PKR)</code>. Accommodates logistics variation per district.</li>
                                <li><strong>Government Contribution:</strong> <code>Gov Share = Approved Bags * District Rate * ({{ $seedSettings['govt_share_pct'] ?? 50 }} / 100)</code>.</li>
                                <li><strong>Farmer Contribution:</strong> <code>Farmer Share = Approved Bags * District Rate * ({{ $seedSettings['farmer_share_pct'] ?? 50 }} / 100)</code>.</li>
                                <li><strong>PII Masking Formula:</strong> CNIC: <code>71501-*****12-3</code>; Mobile: <code>0300-***4567</code>. Unmasked view reserved strictly for authorized executive audit.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
{{-- Load Standalone ApexCharts Library --}}
<script src="{{ asset('admin-assets/assets/scripts/apexcharts.min.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const analyticsUrl = @json(route('admin.wheat-mis.analytics'));
    let charts = {};

    function renderDonut(el, labels, series, colors) {
        const domEl = document.querySelector(el);
        if (!domEl || typeof ApexCharts === 'undefined') return;

        const options = {
            chart: { type: 'donut', height: 320 },
            labels: labels,
            series: series.map(Number),
            colors: colors || ['#eab308', '#0ea5e9', '#3b82f6', '#22c55e', '#ef4444', '#94a3b8'],
            legend: { position: 'bottom' },
            dataLabels: { enabled: true },
            responsive: [{
                breakpoint: 480,
                options: { chart: { width: 280 }, legend: { position: 'bottom' } }
            }]
        };
        if (charts[el]) {
            charts[el].updateOptions(options);
            charts[el].updateSeries(options.series);
            return;
        }
        charts[el] = new ApexCharts(domEl, options);
        charts[el].render();
    }

    function renderGroupedBar(el, categories, seriesData) {
        const domEl = document.querySelector(el);
        if (!domEl || typeof ApexCharts === 'undefined') return;

        const options = {
            chart: { type: 'bar', height: 350, toolbar: { show: false } },
            series: seriesData,
            xaxis: { categories: categories },
            colors: ['#64748b', '#3b82f6', '#22c55e', '#f59e0b'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 3
                }
            },
            dataLabels: { enabled: false },
            legend: { position: 'top' },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: val => val + ' Bags (50kg)' }
            }
        };
        if (charts[el]) {
            charts[el].updateOptions(options);
            charts[el].updateSeries(options.series);
            return;
        }
        charts[el] = new ApexCharts(domEl, options);
        charts[el].render();
    }

    function renderHorizontalBar(el, categories, series, name, color) {
        const domEl = document.querySelector(el);
        if (!domEl || typeof ApexCharts === 'undefined') return;

        const options = {
            chart: { type: 'bar', height: 280, toolbar: { show: false } },
            series: [{ name: name, data: series.map(Number) }],
            xaxis: { categories: categories },
            colors: [color || '#3b82f6'],
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '50%'
                }
            },
            dataLabels: { enabled: true }
        };
        if (charts[el]) {
            charts[el].updateOptions(options);
            charts[el].updateSeries(options.series);
            return;
        }
        charts[el] = new ApexCharts(domEl, options);
        charts[el].render();
    }

    async function fetchAnalytics() {
        const form = document.getElementById('wheat-mis-filters');
        const params = form ? new URLSearchParams(new FormData(form)).toString() : '';

        try {
            const res = await fetch(analyticsUrl + '?' + params, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            // 1. Demand vs Supply Grouped Bar
            renderGroupedBar('#chart-demand-supply', data.demand_supply.labels, [
                { name: 'Allocated Quota (Supply)', data: data.demand_supply.quotas },
                { name: 'Requested Bags (Demand)', data: data.demand_supply.requested },
                { name: 'Approved Bags', data: data.demand_supply.approved },
                { name: 'Disbursed Bags', data: data.demand_supply.disbursed },
            ]);

            // 2. Status Donut
            renderDonut('#chart-status-donut', data.by_status.labels, data.by_status.series);

            // 3. Landholding Bands Bar
            renderHorizontalBar('#chart-land-bands', data.land_bands.labels, data.land_bands.counts, 'Applicants', '#059669');

            // 4. Water Availability Donut
            renderDonut('#chart-water-donut', data.water.labels, data.water.series, ['#0ea5e9', '#ef4444', '#cbd5e1']);

            // 5. Road Access Bar
            renderHorizontalBar('#chart-road-bar', data.road.labels, data.road.series, 'Applicants', '#8b5cf6');

        } catch (e) {
            console.error('Failed to load MIS analytics:', e);
        }
    }

    // --- SEED DATA MODAL LIVE CALCULATOR & DISTRICT SYNC ---
    const rateInput = document.getElementById('input_wheat_seed_bag_rate');
    const govPctInput = document.getElementById('input_wheat_seed_govt_share_pct');
    const farmerPctInput = document.getElementById('input_wheat_seed_farmer_share_pct');

    function syncDistrictRatesTable() {
        const baseRate = parseFloat(rateInput?.value) || 0;
        const govPct = parseFloat(govPctInput?.value || 50);
        const farmerPct = parseFloat(farmerPctInput?.value || 50);

        document.querySelectorAll('.district-rate-row').forEach(row => {
            const previewBase = row.querySelector('.preview-base-rate');
            const transportInput = row.querySelector('.input-district-transport');
            const dRateInput = row.querySelector('.input-district-rate');
            const govShareEl = row.querySelector('.dist-preview-gov');
            const farmerShareEl = row.querySelector('.dist-preview-farmer');

            if (previewBase) {
                previewBase.textContent = baseRate > 0 
                    ? 'PKR ' + baseRate.toLocaleString('en-PK', { maximumFractionDigits: 0 }) 
                    : '—';
            }

            const transportCost = parseFloat(transportInput?.value) || 0;
            let deliveredRate = parseFloat(dRateInput?.value);

            if (baseRate > 0 && dRateInput && (!dRateInput.dataset.manual || dRateInput.dataset.manual === 'false')) {
                deliveredRate = Math.round((baseRate + transportCost) * 100) / 100;
                dRateInput.value = deliveredRate;
            }

            if (!isNaN(deliveredRate) && deliveredRate > 0) {
                const govShare = Math.round((deliveredRate * (govPct / 100)) * 100) / 100;
                const farmerShare = Math.round((deliveredRate * (farmerPct / 100)) * 100) / 100;
                if (govShareEl) govShareEl.textContent = 'PKR ' + govShare.toLocaleString('en-PK', { maximumFractionDigits: 0 });
                if (farmerShareEl) farmerShareEl.textContent = 'PKR ' + farmerShare.toLocaleString('en-PK', { maximumFractionDigits: 0 });
            } else {
                if (govShareEl) govShareEl.textContent = '—';
                if (farmerShareEl) farmerShareEl.textContent = '—';
            }
        });
    }

    function updateSeedCalcPreview() {
        if (!rateInput) return;
        const rate = parseFloat(rateInput.value);
        const govPct = parseFloat(govPctInput?.value || 50);
        const farmerPct = parseFloat(farmerPctInput?.value || 50);

        const rateEl = document.getElementById('calc-preview-rate');
        const govEl = document.getElementById('calc-preview-gov');
        const farmerEl = document.getElementById('calc-preview-farmer');
        const govPctEl = document.getElementById('calc-preview-gov-pct');
        const farmerPctEl = document.getElementById('calc-preview-farmer-pct');
        const badgeEl = document.getElementById('seed-calc-badge');

        if (govPctEl) govPctEl.textContent = !isNaN(govPct) ? govPct : '0';
        if (farmerPctEl) farmerPctEl.textContent = !isNaN(farmerPct) ? farmerPct : '0';
        if (badgeEl) badgeEl.textContent = (!isNaN(govPct) && !isNaN(farmerPct)) ? `${govPct}:${farmerPct} Cost-Sharing` : 'Cost Sharing';

        if (!isNaN(rate) && rate > 0) {
            const govShare = (rate * (govPct / 100));
            const farmerShare = (rate * (farmerPct / 100));
            if (rateEl) rateEl.textContent = 'PKR ' + rate.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (govEl) govEl.textContent = 'PKR ' + govShare.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (farmerEl) farmerEl.textContent = 'PKR ' + farmerShare.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else {
            if (rateEl) rateEl.textContent = '—';
            if (govEl) govEl.textContent = '—';
            if (farmerEl) farmerEl.textContent = '—';
        }

        syncDistrictRatesTable();
    }

    if (rateInput) {
        rateInput.addEventListener('input', updateSeedCalcPreview);
    }
    if (govPctInput) {
        govPctInput.addEventListener('input', function() {
            if (farmerPctInput && !isNaN(parseFloat(this.value))) {
                const val = Math.max(0, Math.min(100, parseFloat(this.value)));
                farmerPctInput.value = 100 - val;
            }
            updateSeedCalcPreview();
        });
    }
    if (farmerPctInput) {
        farmerPctInput.addEventListener('input', function() {
            if (govPctInput && !isNaN(parseFloat(this.value))) {
                const val = Math.max(0, Math.min(100, parseFloat(this.value)));
                govPctInput.value = 100 - val;
            }
            updateSeedCalcPreview();
        });
    }

    // Attach listeners for transport and district rate inputs
    document.querySelectorAll('.input-district-transport').forEach(inp => {
        inp.addEventListener('input', function () {
            const row = this.closest('.district-rate-row');
            const rateInp = row?.querySelector('.input-district-rate');
            const baseRate = parseFloat(rateInput?.value) || 0;
            const transport = parseFloat(this.value) || 0;
            if (baseRate > 0 && rateInp) {
                rateInp.value = Math.round((baseRate + transport) * 100) / 100;
                rateInp.dataset.manual = 'false';
            }
            syncDistrictRatesTable();
        });
    });

    document.querySelectorAll('.input-district-rate').forEach(inp => {
        inp.addEventListener('input', function () {
            this.dataset.manual = 'true';
            const row = this.closest('.district-rate-row');
            const transportInp = row?.querySelector('.input-district-transport');
            const baseRate = parseFloat(rateInput?.value) || 0;
            const delivered = parseFloat(this.value);
            if (baseRate > 0 && !isNaN(delivered) && delivered >= baseRate && transportInp) {
                transportInp.value = Math.round((delivered - baseRate) * 100) / 100;
            }
            syncDistrictRatesTable();
        });
    });

    // --- DATA DICTIONARY INSTANT FILTER ---
    const dictSearch = document.getElementById('dict-search-input');
    const dictCounter = document.getElementById('dict-counter-badge');
    if (dictSearch) {
        dictSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.dict-row');
            let visible = 0;

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (!query || text.includes(query)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (dictCounter) {
                dictCounter.textContent = `${visible} of ${rows.length} Entries`;
            }
        });
    }

    // --- WHEAT SEED BENEFICIARY REGISTRY AJAX FILTER & DYNAMIC EXPORT ---
    const gridFilterForm = document.getElementById('beneficiary-grid-filter-form');
    const gridContainer = document.getElementById('beneficiary-grid-container');
    const gridTotalBadge = document.getElementById('grid-total-badge');
    const gridSpinner = document.getElementById('grid-loading-spinner');
    const beneficiariesUrl = @json(route('admin.wheat-mis.beneficiaries'));
    const exportBaseUrl = @json(route('admin.wheat-mis.export'));

    const exportExcelBtn = document.getElementById('grid-export-excel');
    const exportCsvBtn = document.getElementById('grid-export-csv');
    const exportPdfBtn = document.getElementById('grid-export-pdf');

    let gridAbortController = null;
    let gridDebounceTimer = null;
    let currentGridPage = 1;

    function getGridFilterParams(page = 1) {
        const params = new URLSearchParams();
        if (!gridFilterForm) return params;

        const formData = new FormData(gridFilterForm);
        for (const [key, val] of formData.entries()) {
            const trimmed = String(val).trim();
            if (trimmed !== '') {
                params.append(key, trimmed);
            }
        }

        const perPage = params.get('per_page') || '25';
        if (perPage !== 'all' && page && Number(page) > 1) {
            params.set('page', page);
        }

        return params;
    }

    function syncGridExportUrls() {
        const baseParams = getGridFilterParams(1);

        if (exportExcelBtn) {
            const p = new URLSearchParams(baseParams);
            p.set('format', 'xlsx');
            exportExcelBtn.href = exportBaseUrl + '?' + p.toString();
        }
        if (exportCsvBtn) {
            const p = new URLSearchParams(baseParams);
            p.set('format', 'csv');
            exportCsvBtn.href = exportBaseUrl + '?' + p.toString();
        }
        if (exportPdfBtn) {
            const p = new URLSearchParams(baseParams);
            p.set('format', 'pdf');
            exportPdfBtn.href = exportBaseUrl + '?' + p.toString();
        }
    }

    async function fetchBeneficiaries(page = 1) {
        if (!gridContainer) return;

        currentGridPage = page;
        const params = getGridFilterParams(page);
        syncGridExportUrls();

        if (gridAbortController) {
            gridAbortController.abort();
        }
        gridAbortController = new AbortController();

        if (gridSpinner) gridSpinner.classList.remove('d-none');
        gridContainer.style.opacity = '0.55';
        gridContainer.style.pointerEvents = 'none';

        try {
            const response = await fetch(beneficiariesUrl + '?' + params.toString(), {
                signal: gridAbortController.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Server returned HTTP ' + response.status);
            }

            const data = await response.json();

            if (data.html) {
                gridContainer.innerHTML = data.html;
            }

            if (gridTotalBadge && typeof data.total !== 'undefined') {
                gridTotalBadge.textContent = Number(data.total).toLocaleString('en-US') + ' matching records';
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Failed to filter beneficiary grid:', err);
            }
        } finally {
            if (gridSpinner) gridSpinner.classList.add('d-none');
            gridContainer.style.opacity = '1';
            gridContainer.style.pointerEvents = '';
        }
    }

    if (gridFilterForm) {
        // Dropdown changes trigger immediate AJAX fetch
        gridFilterForm.querySelectorAll('select.grid-filter-input').forEach(sel => {
            sel.addEventListener('change', function () {
                fetchBeneficiaries(1);
            });
        });

        // Text & Number inputs debounced
        gridFilterForm.querySelectorAll('input.grid-filter-input').forEach(inp => {
            inp.addEventListener('input', function () {
                clearTimeout(gridDebounceTimer);
                gridDebounceTimer = setTimeout(() => {
                    fetchBeneficiaries(1);
                }, 350);
            });

            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(gridDebounceTimer);
                    fetchBeneficiaries(1);
                }
            });
        });

        // Reset button
        const resetBtn = document.getElementById('btn-grid-filter-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                gridFilterForm.reset();
                const perPageSelect = document.getElementById('grid-per-page');
                if (perPageSelect) perPageSelect.value = '25';
                fetchBeneficiaries(1);
            });
        }

        // Intercept pagination clicks inside dynamic grid container
        gridContainer.addEventListener('click', function (e) {
            const link = e.target.closest('.wheat-grid-pagination-links a, .pagination a');
            if (!link || !link.href) return;

            e.preventDefault();
            try {
                const url = new URL(link.href, window.location.origin);
                const page = url.searchParams.get('page') || 1;
                fetchBeneficiaries(page);

                // Smooth scroll to grid top
                const card = document.getElementById('wheat-beneficiaries-card');
                if (card) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } catch (err) {
                console.error('Error handling pagination click:', err);
            }
        });

        // Sync initial export links
        syncGridExportUrls();
    }

    function initWhenReady(tries = 40) {
        if (typeof ApexCharts !== 'undefined') {
            fetchAnalytics();
        } else if (tries > 0) {
            setTimeout(() => initWhenReady(tries - 1), 50);
        } else {
            console.error('ApexCharts library could not be loaded.');
        }
    }

    initWhenReady();
});
</script>
@endpush

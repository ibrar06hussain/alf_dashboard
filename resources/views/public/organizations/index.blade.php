@extends('layouts.public')

@section('title', 'Farmer & Village Organizations - Gilgit-Baltistan')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        .gb-cluster-marker {
            display: flex;
            flex-direction: column;
            align-items: center;
            transform: translate(-50%, -50%);
            cursor: pointer;
        }

        .gb-cluster-bubble {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #ffffff;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.35), 0 0 0 4px rgba(255, 255, 255, 0.85);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            line-height: 1.05;
        }

        .gb-cluster-marker:hover .gb-cluster-bubble {
            transform: scale(1.12);
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.45), 0 0 0 5px #ffffff;
        }

        .gb-cluster-bubble--district {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #15803d, #1b4332);
            font-size: 1rem;
        }

        .gb-cluster-bubble--tehsil {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            font-size: 0.9rem;
        }

        .gb-cluster-bubble--uc {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #7c3aed, #5b21b6);
            font-size: 0.8rem;
        }

        .gb-cluster-sub {
            font-size: 0.62rem;
            font-weight: 600;
            opacity: 0.9;
        }

        .gb-cluster-label {
            margin-top: 4px;
            background: rgba(15, 23, 42, 0.88);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        }

        .map-level-btn.is-active {
            background-color: #ffffff !important;
            color: #1b4332 !important;
            font-weight: 800 !important;
        }

        .org-kpi-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 22px 24px;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }

        .org-kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .org-kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .org-panel {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .org-panel-header {
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            color: #ffffff;
            padding: 18px 24px;
        }

        .org-panel-header h4 {
            color: #ffffff;
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .org-summary-table th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-bottom: 2px solid #e2e8f0;
            padding: 13px 16px;
        }

        .org-summary-table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.93rem;
            color: #1e293b;
        }

        .org-summary-table tbody tr:hover {
            background-color: #f1f8e9;
        }

        .org-summary-table tfoot th,
        .org-summary-table tfoot td {
            background-color: #ecfdf5;
            font-weight: 800;
            color: #065f46;
            border-top: 2px solid #10b981;
            padding: 14px 16px;
        }

        .sheet-tabs-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .sheet-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            transition: all 0.15s ease;
        }

        .sheet-tab-btn:hover {
            border-color: #77b122;
            color: #2d6a4f;
            background: #f4f8ec;
        }

        .sheet-tab-btn.is-active {
            background: #77b122;
            border-color: #77b122;
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(119, 177, 34, 0.3);
        }

        .sheet-tab-badge {
            background: rgba(15, 23, 42, 0.08);
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.73rem;
        }

        .sheet-tab-btn.is-active .sheet-tab-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        .vo-badge-formed {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .vo-badge-unformed {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .progress-thin {
            height: 8px;
            border-radius: 999px;
            background-color: #e2e8f0;
            overflow: hidden;
        }

        .progress-thin-bar {
            height: 100%;
            background: linear-gradient(90deg, #77b122, #2d6a4f);
            border-radius: 999px;
        }

        .org-map-box {
            width: 100%;
            height: 520px;
            z-index: 1;
        }

        .org-chart-box {
            position: relative;
            height: 340px;
        }

        .org-doughnut-box {
            position: relative;
            height: 240px;
        }

        .map-mode-btn-group {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        @media (max-width: 767.98px) {
            .section-padding-80 {
                padding-top: 28px !important;
                padding-bottom: 36px !important;
            }

            .breadcrumb-area {
                min-height: 190px !important;
                padding: 32px 0 !important;
            }

            .breadcrumb-text h2 {
                font-size: 1.4rem !important;
            }

            .breadcrumb-text p {
                font-size: 0.84rem !important;
            }

            .org-kpi-card {
                padding: 14px;
                border-radius: 12px;
            }

            .org-kpi-card h2 {
                font-size: 1.35rem;
            }

            .org-kpi-icon {
                width: 38px;
                height: 38px;
                font-size: 15px;
                border-radius: 10px;
            }

            .org-panel {
                border-radius: 12px;
            }

            .org-panel-header {
                padding: 13px 15px;
            }

            .org-panel-header h4 {
                font-size: 0.98rem;
                line-height: 1.35;
            }

            .org-map-box {
                height: 360px !important;
            }

            .org-chart-box {
                height: 260px !important;
            }

            .org-doughnut-box {
                height: 200px !important;
            }

            .gb-cluster-bubble--district {
                width: 46px;
                height: 46px;
                font-size: 0.85rem;
            }

            .gb-cluster-bubble--tehsil {
                width: 40px;
                height: 40px;
                font-size: 0.78rem;
            }

            .gb-cluster-bubble--uc {
                width: 32px;
                height: 32px;
                font-size: 0.72rem;
            }

            .gb-cluster-label {
                font-size: 0.65rem;
                padding: 1px 6px;
            }

            .sheet-tabs-wrap {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding: 12px 14px;
                scrollbar-width: thin;
            }

            .sheet-tab-btn {
                flex-shrink: 0;
                padding: 6px 12px;
                font-size: 0.78rem;
            }

            .org-summary-table th,
            .org-summary-table td {
                padding: 9px 10px;
                font-size: 0.82rem;
            }

            .global-filter-collapse:not(.is-expanded) {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    <section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax" data-alf-fit="bg"
        data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
        data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
        style="background-image: url('{{ \App\Support\Media::site('about_breadcrumb', 'frontend/img/bg-img/5.jpg') }}');">
        <div class="container h-100">
            <div class="row h-100 align-items-center">
                <div class="col-12">
                    <div class="breadcrumb-text text-center">
                        <h2 class="text-white">Village Agriculture Committees (GB)</h2>
                        <p class="text-white-50 mb-2">District-wise Summary &amp; Directory of Village Agriculture
                            Committees Gilgit-Baltistan</p>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb justify-content-center">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}"
                                        class="text-white">{{ __('portal.home') }}</a></li>
                                <li class="breadcrumb-item active text-white" aria-current="page">Organizations</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-padding-80" style="background-color: #f8fafc;">
        <div class="container">

            {{-- Top Action Banner for Logged-In Admin + Excel Download --}}
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <span class="badge bg-success mb-1">Official Directory</span>
                    <h3 class="mb-0 fw-bold" style="color: #1e293b;">List of Village Agriculture Committees —
                        Gilgit-Baltistan</h3>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if(file_exists(public_path('docs/farmer_organizations_gb.xlsx')))
                        <a href="{{ asset('docs/farmer_organizations_gb.xlsx') }}" download
                            class="btn btn-outline-success btn-sm fw-bold px-3 py-2">
                            <i class="fa-solid fa-file-excel me-1"></i> Download Excel Workbook
                        </a>
                    @endif
                    @auth
                        <a href="{{ route('admin.organizations.index') }}" class="btn btn-success btn-sm fw-bold px-3 py-2">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Manage Organizations (Admin CRUD)
                        </a>
                    @endauth
                </div>
            </div>

            {{-- 0. Page-Wide Global Filter Bar (District, Tehsil / Sub-Division, Union Council, Status, Search) --}}
            @php
                $hasGlobalFilters = $activeDivision !== '' || $activeDistrict !== '' || $activeSheet !== '' || $activeTehsil !== '' || $activeUc !== '' || $voStatus !== '' || $search !== '';
            @endphp
            <div class="org-panel mb-4">
                <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-3 px-4">
                    <div class="d-flex align-items-center justify-content-between w-100 w-md-auto">
                        <h4 class="mb-0"><i class="fa-solid fa-sliders me-2"></i>Filter Entire Page (District, Tehsil &amp;
                            UC)</h4>
                        <button type="button" class="btn btn-sm btn-light fw-bold d-md-none ms-2"
                            onclick="document.getElementById('globalFilterCollapseBox').classList.toggle('is-expanded')">
                            <i class="fa-solid fa-filter me-1"></i> Filters
                        </button>
                    </div>
                    @if($hasGlobalFilters)
                        <a href="{{ route('organizations.index') }}" class="btn btn-sm btn-warning fw-bold text-dark">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset All Filters
                        </a>
                    @endif
                </div>
                <div id="globalFilterCollapseBox"
                    class="p-3 p-md-4 bg-white global-filter-collapse {{ $hasGlobalFilters ? 'is-expanded' : '' }}">
                    <form method="GET" action="{{ route('organizations.index') }}" id="globalPageFilterForm"
                        class="row g-2 g-md-3 align-items-end">
                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalDivisionSelect"
                                class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Division
                            </label>
                            <select id="globalDivisionSelect" name="division" class="form-select form-select-sm"
                                onchange="onGlobalDivisionChange()">
                                <option value="">All Divisions (3)</option>
                                <option value="Gilgit" @selected($activeDivision === 'Gilgit')>Gilgit Division</option>
                                <option value="Baltistan" @selected($activeDivision === 'Baltistan')>Baltistan Division
                                </option>
                                <option value="Diamer" @selected($activeDivision === 'Diamer')>Diamer Division</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalDistrictSelect"
                                class="form-label small fw-bold text-uppercase text-muted mb-1">
                                District
                            </label>
                            <select id="globalDistrictSelect" name="district" class="form-select form-select-sm"
                                onchange="onGlobalDistrictChange()">
                                <option value="">All Districts (10)</option>
                                @foreach(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP as $code => $meta)
                                    <option value="{{ $meta['district'] }}" data-division="{{ $meta['division'] }}"
                                        data-sheet="{{ $code }}" @selected($activeDistrict === $meta['district'] || $activeSheet === $code)>
                                        {{ $meta['district'] }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalTehsilSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Tehsil / Sub-Div
                            </label>
                            <select id="globalTehsilSelect" name="tehsil" class="form-select form-select-sm"
                                onchange="onGlobalTehsilChange()">
                                <option value="">All Tehsils ({{ count($tehsilOptions) }})</option>
                                @foreach($tehsilOptions as $tOpt)
                                    <option value="{{ $tOpt['name'] }}" data-district="{{ $tOpt['district'] }}"
                                        data-division="{{ $tOpt['division'] }}" @selected($activeTehsil === $tOpt['name'])>
                                        {{ $tOpt['name'] }} ({{ $tOpt['district'] }} - {{ $tOpt['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalUcSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Union Council / MC
                            </label>
                            <select id="globalUcSelect" name="uc" class="form-select form-select-sm"
                                onchange="document.getElementById('globalPageFilterForm').submit()">
                                <option value="">All UCs ({{ count($ucOptions) }})</option>
                                @foreach($ucOptions as $uOpt)
                                    <option value="{{ $uOpt['name'] }}" data-district="{{ $uOpt['district'] }}"
                                        data-tehsil="{{ $uOpt['tehsil'] }}" data-division="{{ $uOpt['division'] }}"
                                        @selected($activeUc === $uOpt['name'])>
                                        {{ $uOpt['name'] }} ({{ $uOpt['tehsil'] }} - {{ $uOpt['count'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalVoStatusSelect"
                                class="form-label small fw-bold text-uppercase text-muted mb-1">
                                VO Status
                            </label>
                            <select id="globalVoStatusSelect" name="vo_status" class="form-select form-select-sm"
                                onchange="document.getElementById('globalPageFilterForm').submit()">
                                <option value="">All Villages</option>
                                <option value="formed" @selected($voStatus === 'formed')>VO Formed Only</option>
                                <option value="not_formed" @selected($voStatus === 'not_formed')>No VO Formed Yet</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-4 col-lg-2">
                            <label for="globalKeywordInput" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Keyword Search
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="globalKeywordInput" name="q" value="{{ $search }}"
                                    class="form-control" placeholder="Village / VO / Phone...">
                                <button type="submit" class="btn btn-success fw-bold" title="Apply Filters">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    @if($hasGlobalFilters)
                        <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center gap-2">
                            <span class="small fw-bold text-muted text-uppercase me-1"><i
                                    class="fa-solid fa-filter me-1 text-success"></i>Active Scope:</span>
                            @if($activeDivision !== '')
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Division:
                                    {{ $activeDivision }}</span>
                            @endif
                            @if($activeDistrict !== '')
                                <span class="badge bg-success text-white px-2 py-1">District: {{ $activeDistrict }}
                                    ({{ $activeSheet }})</span>
                            @endif
                            @if($activeTehsil !== '')
                                <span class="badge bg-info text-dark px-2 py-1">Tehsil: {{ $activeTehsil }}</span>
                            @endif
                            @if($activeUc !== '')
                                <span class="badge bg-primary text-white px-2 py-1">UC: {{ $activeUc }}</span>
                            @endif
                            @if($voStatus !== '')
                                <span class="badge bg-warning text-dark px-2 py-1">Status:
                                    {{ $voStatus === 'formed' ? 'VOs Formed' : 'No VOs Formed' }}</span>
                            @endif
                            @if($search !== '')
                                <span class="badge bg-dark text-white px-2 py-1">Search: "{{ $search }}"</span>
                            @endif
                            <span class="ms-md-auto small text-muted fw-semibold">
                                Showing <strong>{{ number_format($totals['total_villages']) }}</strong> of
                                {{ number_format($totals['unfiltered_total'] ?? $totals['total_villages']) }} villages
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 1. KPI Summary Cards (2x2 on mobile, 4-across on desktop) --}}
            <div class="row g-2 g-md-4 mb-4 mb-md-5">
                <div class="col-6 col-lg-3">
                    <div class="org-kpi-card">
                        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
                            <span class="text-muted fw-bold small text-uppercase">Total Villages</span>
                            <span class="org-kpi-icon" style="background: #e0f2fe; color: #0284c7;">
                                <i class="fa-solid fa-mountain-city"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1" style="color: #0f172a;">{{ number_format($totals['total_villages']) }}</h2>
                        <p class="small text-muted mb-0 d-none d-sm-block">
                            {{ $hasGlobalFilters ? 'In selected filter scope' : 'Across all 10 Districts of GB' }}</p>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="org-kpi-card">
                        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
                            <span class="text-muted fw-bold small text-uppercase">VOs Formed</span>
                            <span class="org-kpi-icon" style="background: #dcfce7; color: #16a34a;">
                                <i class="fa-solid fa-people-group"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1" style="color: #15803d;">{{ number_format($totals['vos_formed']) }}</h2>
                        <p class="small text-muted mb-0 d-none d-sm-block">Villages where VOs / Co-ops exist</p>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="org-kpi-card">
                        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
                            <span class="text-muted fw-bold small text-uppercase">No VOs Formed</span>
                            <span class="org-kpi-icon" style="background: #fef3c7; color: #d97706;">
                                <i class="fa-solid fa-circle-exclamation"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1" style="color: #b45309;">{{ number_format($totals['no_vos_formed']) }}</h2>
                        <p class="small text-muted mb-0 d-none d-sm-block">Villages where no VOs formed yet</p>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="org-kpi-card">
                        <div class="d-flex align-items-center justify-content-between mb-2 mb-md-3">
                            <span class="text-muted fw-bold small text-uppercase">VO Coverage</span>
                            <span class="org-kpi-icon" style="background: #f3e8ff; color: #7e22ce;">
                                <i class="fa-solid fa-chart-pie"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1" style="color: #6b21a8;">{{ $totals['coverage_pct'] }}%</h2>
                        <div class="progress-thin mt-2">
                            <div class="progress-thin-bar" style="width: {{ min(100, $totals['coverage_pct']) }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Graphical Representation (Charts) --}}
            <div class="row g-3 g-md-4 mb-4 mb-md-5">
                <div class="col-12 col-lg-8">
                    <div class="org-panel h-100">
                        <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h4><i class="fa-solid fa-chart-column me-2"></i>{{ $chartLevelLabel }} Village
                                    Agriculture Committees Comparison</h4>
                                <small class="text-white-50">Total Villages vs. VOs Formed vs. No VOs Formed
                                    ({{ count($chartRows) }}
                                    {{ $activeTehsil ? 'UCs' : ($activeDistrict ? 'Tehsils' : 'Districts') }})</small>
                            </div>
                            <span class="badge bg-light text-dark">{{ $chartLevelLabel }}</span>
                        </div>
                        <div class="p-3 p-md-4">
                            <div class="org-chart-box">
                                <canvas id="districtBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="org-panel h-100">
                        <div class="org-panel-header">
                            <h4><i class="fa-solid fa-chart-pie me-2"></i>Overall GB Formation Status</h4>
                            <small class="text-white-50">Proportion of Villages with Active VOs</small>
                        </div>
                        <div class="p-3 p-md-4 d-flex flex-column justify-content-between">
                            <div class="org-doughnut-box">
                                <canvas id="overallDoughnutChart"></canvas>
                            </div>
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold text-success"><i class="fa-solid fa-circle me-1"></i>Villages
                                        where VOs Formed</span>
                                    <span class="fw-bold">{{ number_format($totals['vos_formed']) }}
                                        ({{ $totals['coverage_pct'] }}%)</span>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span class="fw-semibold text-warning"><i class="fa-solid fa-circle me-1"></i>Villages
                                        where No VOs Formed</span>
                                    <span class="fw-bold">{{ number_format($totals['no_vos_formed']) }}
                                        ({{ round(100 - $totals['coverage_pct'], 1) }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2.5 Interactive Gilgit-Baltistan Multi-Level Cluster Map --}}
            <div class="org-panel mb-4 mb-md-5">
                <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h4><i class="fa-solid fa-map-location-dot me-2"></i>Gilgit-Baltistan Village Agriculture Committees
                            Geospatial
                            Cluster Map</h4>
                        <small class="text-white-50 d-none d-sm-inline">
                            Zoom in or tap a cluster to drill down from <strong>District Level</strong> &rarr;
                            <strong>Tehsil / Sub-Division Level</strong> &rarr; <strong>Union Council (UC) Level</strong>
                        </small>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span id="gbMapLevelIndicator" class="badge bg-warning text-dark fw-bold px-2 py-1">
                            Level: District Clusters
                        </span>
                        <div class="map-mode-btn-group" role="group" aria-label="Map Cluster Level">
                            <button type="button" class="btn btn-sm btn-outline-light map-level-btn is-active"
                                data-mode="auto" onclick="setGbMapMode('auto', this)">
                                Auto
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light map-level-btn" data-mode="district"
                                onclick="setGbMapMode('district', this)">
                                Districts ({{ count($mapClusters['districts']) }})
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light map-level-btn" data-mode="tehsil"
                                onclick="setGbMapMode('tehsil', this)">
                                Tehsils ({{ count($mapClusters['tehsils']) }})
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-light map-level-btn" data-mode="uc"
                                onclick="setGbMapMode('uc', this)">
                                UCs ({{ count($mapClusters['ucs']) }})
                            </button>
                            <button type="button" class="btn btn-sm btn-light fw-bold" onclick="resetGbMapView()">
                                <i class="fa-solid fa-expand me-1"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="px-3 px-md-4 py-2 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                    <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3">
                        <span><span class="badge rounded-pill" style="background:#15803d;">&nbsp;</span>
                            <strong>District</strong> (&le; 8)</span>
                        <span><span class="badge rounded-pill" style="background:#0284c7;">&nbsp;</span>
                            <strong>Tehsil</strong> (9–10)</span>
                        <span><span class="badge rounded-pill" style="background:#7c3aed;">&nbsp;</span> <strong>Union
                                Council</strong> (&ge; 11)</span>
                    </div>
                    <span class="text-muted d-none d-md-inline"><i class="fa-solid fa-hand-pointer me-1"></i>Tap any cluster
                        bubble to zoom
                        in or view VOs formed breakdown</span>
                </div>

                <div id="gbOrganizationsMap" class="org-map-box"></div>
            </div>

            {{-- 3. Summary Sheet Table (Matching the Summary tab of the Excel Workbook) --}}
            <div class="org-panel mb-5">
                <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h4><i class="fa-solid fa-table-list me-2"></i>Detailed Summary of Village Level Committees
                            (District-Wise)</h4>
                        <small class="text-white-50">Click any District row or "View Sheet" button to filter the
                            village-level directory below</small>
                    </div>
                    @if($activeSheet !== '' || $activeDistrict !== '')
                        <a href="{{ route('organizations.index') }}#directory-section" class="btn btn-sm btn-light fw-bold">
                            <i class="fa-solid fa-rotate-left me-1"></i> Show All Districts
                        </a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table org-summary-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Sr.No.</th>
                                <th>District</th>
                                <th>Workbook Sheet</th>
                                <th>Division</th>
                                <th class="text-center">No. of Villages</th>
                                <th class="text-center">No. Villages where VOs Formed</th>
                                <th class="text-center">No. Villages where no VOs Formed</th>
                                <th style="width: 170px;">VO Coverage</th>
                                <th class="text-end">Directory</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summaryRows as $row)
                                @php
                                    $isSelected = ($activeSheet === $row['sheet_code']) || ($activeDistrict === $row['district']);
                                @endphp
                                <tr class="{{ $isSelected ? 'table-success' : '' }}">
                                    <td class="fw-bold text-muted">{{ $row['sr'] }}</td>
                                    <td class="fw-bold">
                                        <a href="{{ route('organizations.index', ['sheet' => $row['sheet_code']]) }}#directory-section"
                                            class="text-decoration-none fw-bold" style="color: #1b4332;">
                                            {{ $row['district'] }}
                                        </a>
                                    </td>
                                    <td>
                                        <span
                                            class="badge bg-secondary bg-opacity-10 text-dark border">{{ $row['sheet_code'] }}</span>
                                    </td>
                                    <td class="text-muted">{{ $row['division'] }}</td>
                                    <td class="text-center fw-bold">{{ number_format($row['total_villages']) }}</td>
                                    <td class="text-center">
                                        <span class="vo-badge-formed">{{ number_format($row['vos_formed']) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($row['no_vos_formed'] > 0)
                                            <span class="vo-badge-unformed">{{ number_format($row['no_vos_formed']) }}</span>
                                        @else
                                            <span class="text-muted small">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-thin flex-grow-1">
                                                <div class="progress-thin-bar"
                                                    style="width: {{ min(100, $row['coverage_pct']) }}%;"></div>
                                            </div>
                                            <span class="small fw-bold"
                                                style="min-width: 45px;">{{ $row['coverage_pct'] }}%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('organizations.index', ['sheet' => $row['sheet_code']]) }}#directory-section"
                                            class="btn btn-sm {{ $isSelected ? 'btn-success' : 'btn-outline-success' }} fw-semibold">
                                            View Sheet <i class="fa-solid fa-arrow-down ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end text-uppercase">Total
                                    ({{ $activeDistrict ?: 'Gilgit-Baltistan' }})</td>
                                <td class="text-center">{{ number_format($totals['total_villages']) }}</td>
                                <td class="text-center">{{ number_format($totals['vos_formed']) }}</td>
                                <td class="text-center">{{ number_format($totals['no_vos_formed']) }}</td>
                                <td>{{ $totals['coverage_pct'] }}% Overall</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($activeDistrict !== '' || $activeTehsil !== '')
                {{-- 3.5 Sub-Division / Tehsil or Union Council Breakdown Table --}}
                <div class="org-panel mb-5">
                    <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h4><i class="fa-solid fa-sitemap me-2"></i>{{ $chartLevelLabel }} Breakdown Summary</h4>
                            <small class="text-white-50">Click any Tehsil or Union Council row to filter the entire page to that
                                level</small>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table org-summary-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 70px;">Sr.No.</th>
                                    <th>{{ $activeTehsil ? 'Union Council / MC' : 'Tehsil / Sub-Division' }}</th>
                                    <th>Parent Jurisdiction</th>
                                    <th class="text-center">No. of Villages</th>
                                    <th class="text-center">VOs Formed</th>
                                    <th class="text-center">No VOs Formed</th>
                                    <th style="width: 170px;">VO Coverage</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($chartRows as $cRow)
                                    <tr>
                                        <td class="fw-bold text-muted">{{ $cRow['sr'] }}</td>
                                        <td class="fw-bold">{{ $cRow['label'] }}</td>
                                        <td class="text-muted">{{ $cRow['parent'] }}</td>
                                        <td class="text-center fw-bold">{{ number_format($cRow['total_villages']) }}</td>
                                        <td class="text-center"><span
                                                class="vo-badge-formed">{{ number_format($cRow['vos_formed']) }}</span></td>
                                        <td class="text-center">
                                            @if($cRow['no_vos_formed'] > 0)
                                                <span class="vo-badge-unformed">{{ number_format($cRow['no_vos_formed']) }}</span>
                                            @else
                                                <span class="text-muted small">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress-thin flex-grow-1">
                                                    <div class="progress-thin-bar"
                                                        style="width: {{ min(100, $cRow['coverage_pct']) }}%;"></div>
                                                </div>
                                                <span class="small fw-bold"
                                                    style="min-width: 45px;">{{ $cRow['coverage_pct'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            @if($cRow['uc'] !== '')
                                                <a href="{{ route('organizations.index', array_filter(['district' => $cRow['district'], 'tehsil' => $cRow['tehsil'], 'uc' => $cRow['uc'], 'vo_status' => $voStatus])) }}"
                                                    class="btn btn-sm btn-outline-success fw-semibold">
                                                    Filter UC
                                                </a>
                                            @else
                                                <a href="{{ route('organizations.index', array_filter(['district' => $cRow['district'], 'tehsil' => $cRow['tehsil'], 'vo_status' => $voStatus])) }}"
                                                    class="btn btn-sm btn-outline-success fw-semibold">
                                                    Drill into Tehsil
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- 4. Village Level Organizations Directory (Workbook Tabs + Search + Table) --}}
            <div id="directory-section" class="org-panel">
                <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h4>
                            <i class="fa-solid fa-building-wheat me-2"></i>
                            List of Village Level Committees
                            @if($activeSheet && isset(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]))
                                — {{ \App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]['district'] }}
                                ({{ $activeSheet }})
                            @elseif($activeDistrict)
                                — {{ $activeDistrict }}
                            @else
                                — All Districts
                            @endif
                        </h4>
                        <small class="text-white-50">Search by Village Name, Cooperative / VO Name, Sub-Division, UC/MC,
                            Focal Person, or Phone Number</small>
                    </div>
                    <span class="badge bg-white text-dark fw-bold px-3 py-2">
                        Showing {{ $organizations->firstItem() ?? 0 }}–{{ $organizations->lastItem() ?? 0 }} of
                        {{ number_format($organizations->total()) }} Records
                    </span>
                </div>

                {{-- District Workbook Sheet Tabs --}}
                <div class="sheet-tabs-wrap" role="tablist" aria-label="District Workbook Sheets">
                    <a href="{{ route('organizations.index', array_filter(['q' => $search, 'vo_status' => $voStatus])) }}#directory-section"
                        class="sheet-tab-btn {{ $activeSheet === '' && $activeDistrict === '' ? 'is-active' : '' }}">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>All Workbooks</span>
                        <span class="sheet-tab-badge">{{ $totals['total_villages'] }}</span>
                    </a>
                    @foreach($summaryRows as $row)
                        <a href="{{ route('organizations.index', array_filter(['sheet' => $row['sheet_code'], 'q' => $search, 'vo_status' => $voStatus])) }}#directory-section"
                            class="sheet-tab-btn {{ $activeSheet === $row['sheet_code'] ? 'is-active' : '' }}"
                            title="{{ $row['district'] }} District ({{ $row['division'] }} Division)">
                            <span>{{ $row['sheet_code'] }} ({{ $row['district'] }})</span>
                            <span class="sheet-tab-badge">{{ $row['total_villages'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- Search & Filter Bar --}}
                <div class="p-4 border-bottom bg-white">
                    <form method="GET" action="{{ route('organizations.index') }}#directory-section"
                        class="row g-3 align-items-end">
                        @if($activeSheet !== '')
                            <input type="hidden" name="sheet" value="{{ $activeSheet }}">
                        @endif

                        <div class="col-12 col-md-5">
                            <label for="orgSearchInput" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Search Village, Committee /VO, UC/MC, or
                                Focal Person
                            </label>
                            <input type="text" id="orgSearchInput" name="q" value="{{ $search }}" class="form-control"
                                placeholder="Type village, cooperative/VO name, focal person, or phone...">
                        </div>

                        <div class="col-12 col-sm-6 col-md-3">
                            <label for="voStatusSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Organization Status
                            </label>
                            <select id="voStatusSelect" name="vo_status" class="form-select">
                                <option value="">All Villages (Formed &amp; Not Formed)</option>
                                <option value="formed" @selected($voStatus === 'formed')>VO Formed Only
                                    ({{ $totals['vos_formed'] }})</option>
                                <option value="not_formed" @selected($voStatus === 'not_formed')>No VO Formed Yet
                                    ({{ $totals['no_vos_formed'] }})</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-md-2">
                            <label for="divisionSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                                Division
                            </label>
                            <select id="divisionSelect" name="division" class="form-select">
                                <option value="">All Divisions</option>
                                <option value="Gilgit" @selected($activeDivision === 'Gilgit')>Gilgit Division</option>
                                <option value="Baltistan" @selected($activeDivision === 'Baltistan')>Baltistan Division
                                </option>
                                <option value="Diamer" @selected($activeDivision === 'Diamer')>Diamer Division</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-success fw-bold flex-grow-1">
                                <i class="fa-solid fa-filter me-1"></i> Search
                            </button>
                            @if($search !== '' || $voStatus !== '' || $activeDivision !== '' || $activeSheet !== '')
                                <a href="{{ route('organizations.index') }}#directory-section" class="btn btn-outline-secondary"
                                    title="Reset all filters">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                {{-- Village Level Organizations Table --}}
                <div class="table-responsive">
                    <table class="table org-summary-table mb-0" id="villageOrgTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">S#</th>
                                <th>Division</th>
                                <th>District</th>
                                <th>Sub Division</th>
                                <th>UC / MC</th>
                                <th>Name of Village</th>
                                <th>Name of Existing Agri Cooperative / CBO / Farmer Organization / VO</th>
                                <th>Name &amp; Contact of Focal Person</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($organizations as $index => $org)
                                <tr>
                                    <td class="text-muted fw-semibold">{{ ($organizations->firstItem() ?? 1) + $index }}</td>
                                    <td>{{ $org->division_name ?: '—' }}</td>
                                    <td>
                                        <span class="fw-bold" style="color: #1b4332;">{{ $org->district_name }}</span>
                                        <span class="badge bg-light text-muted border ms-1">{{ $org->sheet_code }}</span>
                                    </td>
                                    <td>{{ $org->sub_division ?: '—' }}</td>
                                    <td>{{ $org->uc_mc ?: '—' }}</td>
                                    <td class="fw-bold">{{ $org->village_name }}</td>
                                    <td>
                                        @if($org->is_vo_formed && $org->organization_name)
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fa-solid fa-circle-check text-success mt-1"></i>
                                                <span class="fw-semibold text-dark">{{ $org->organization_name }}</span>
                                            </div>
                                        @else
                                            <span class="vo-badge-unformed">
                                                <i class="fa-solid fa-clock"></i> Not Formed Yet
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($org->focal_person_name || $org->focal_person_phone)
                                            <div class="fw-semibold text-dark">
                                                {{ $org->focal_person_name ?: $org->focal_person_raw }}
                                            </div>
                                            @if($org->focal_person_phone)
                                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $org->focal_person_phone) }}"
                                                    class="small text-success text-decoration-none fw-bold">
                                                    <i class="fa-solid fa-phone me-1"></i>{{ $org->focal_person_phone }}
                                                </a>
                                            @endif
                                        @elseif($org->focal_person_raw)
                                            <span class="small text-muted">{{ $org->focal_person_raw }}</span>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fa-solid fa-magnifying-glass fa-2x mb-2 d-block"></i>
                                            No Village / Farmer Committees records matched your filter criteria.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($organizations->hasPages())
                    <div class="p-4 border-top bg-light d-flex justify-content-center">
                        {{ $organizations->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>

        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        // --- Gilgit-Baltistan Multi-Level Cluster Map ---
        let gbMap = null;
        let gbMarkerLayer = null;
        let gbMapMode = 'auto';
        const gbClusters = @json($mapClusters);
        const gbBaseDirectoryUrl = @json(route('organizations.index'));

        function getActiveClusterLevel() {
            if (gbMapMode !== 'auto') return gbMapMode;
            if (!gbMap) return 'district';
            const z = gbMap.getZoom();
            if (z <= 8) return 'district';
            if (z <= 10) return 'tehsil';
            return 'uc';
        }

        function renderGbMapClusters() {
            if (!gbMap || !gbMarkerLayer) return;
            gbMarkerLayer.clearLayers();

            const level = getActiveClusterLevel();
            const indicator = document.getElementById('gbMapLevelIndicator');

            if (level === 'district') {
                if (indicator) indicator.textContent = 'Level: District Clusters (' + gbClusters.districts.length + ')';
                gbClusters.districts.forEach(item => {
                    const pct = item.total > 0 ? Math.round((item.formed / item.total) * 100) : 0;
                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div class="gb-cluster-marker" title="${item.name} District: ${item.total} Villages (${item.formed} VOs Formed)">
                                <div class="gb-cluster-bubble gb-cluster-bubble--district">
                                    <span>${item.total}</span>
                                    <span class="gb-cluster-sub">${item.formed} VOs</span>
                                </div>
                                <div class="gb-cluster-label">${item.name}</div>
                            </div>
                        `,
                        iconSize: [0, 0]
                    });

                    const marker = L.marker([item.lat, item.lng], { icon });
                    const filterUrl = `${gbBaseDirectoryUrl}?sheet=${encodeURIComponent(item.sheet_code)}#directory-section`;
                    marker.bindPopup(`
                        <div style="min-width: 220px;">
                            <div class="fw-bold fs-6 mb-1" style="color:#1b4332;">${item.name} District (${item.sheet_code})</div>
                            <div class="small text-muted mb-2">${item.division} Division</div>
                            <div class="d-flex justify-content-between small border-bottom py-1">
                                <span>No. of Villages:</span>
                                <strong>${item.total}</strong>
                            </div>
                            <div class="d-flex justify-content-between small border-bottom py-1 text-success">
                                <span>VOs Formed:</span>
                                <strong>${item.formed} (${pct}%)</strong>
                            </div>
                            <div class="d-flex justify-content-between small py-1 text-warning">
                                <span>No VOs Formed:</span>
                                <strong>${item.not_formed}</strong>
                            </div>
                            <div class="d-flex gap-2 mt-2 pt-1">
                                <button type="button" class="btn btn-sm btn-primary flex-grow-1" onclick="zoomToGbLocation(${item.lat}, ${item.lng}, 10)">
                                    Zoom to Tehsils
                                </button>
                                <a href="${filterUrl}" class="btn btn-sm btn-outline-success">View List</a>
                            </div>
                        </div>
                    `);
                    gbMarkerLayer.addLayer(marker);
                });
            } else if (level === 'tehsil') {
                if (indicator) indicator.textContent = 'Level: Tehsil / Sub-Division Clusters (' + gbClusters.tehsils.length + ')';
                gbClusters.tehsils.forEach(item => {
                    const pct = item.total > 0 ? Math.round((item.formed / item.total) * 100) : 0;
                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div class="gb-cluster-marker" title="Tehsil ${item.name} (${item.district}): ${item.total} Villages">
                                <div class="gb-cluster-bubble gb-cluster-bubble--tehsil">
                                    <span>${item.total}</span>
                                    <span class="gb-cluster-sub">${item.formed} VO</span>
                                </div>
                                <div class="gb-cluster-label">${item.name}</div>
                            </div>
                        `,
                        iconSize: [0, 0]
                    });

                    const marker = L.marker([item.lat, item.lng], { icon });
                    const filterUrl = `${gbBaseDirectoryUrl}?sheet=${encodeURIComponent(item.sheet_code)}&q=${encodeURIComponent(item.name)}#directory-section`;
                    marker.bindPopup(`
                        <div style="min-width: 220px;">
                            <div class="fw-bold fs-6 mb-0" style="color:#0369a1;">Tehsil / Sub-Div: ${item.name}</div>
                            <div class="small text-muted mb-2">District: ${item.district}</div>
                            <div class="d-flex justify-content-between small border-bottom py-1">
                                <span>Total Villages:</span>
                                <strong>${item.total}</strong>
                            </div>
                            <div class="d-flex justify-content-between small border-bottom py-1 text-success">
                                <span>VOs Formed:</span>
                                <strong>${item.formed} (${pct}%)</strong>
                            </div>
                            <div class="d-flex justify-content-between small py-1 text-warning">
                                <span>No VOs Formed:</span>
                                <strong>${item.not_formed}</strong>
                            </div>
                            <div class="d-flex gap-2 mt-2 pt-1">
                                <button type="button" class="btn btn-sm btn-primary flex-grow-1" onclick="zoomToGbLocation(${item.lat}, ${item.lng}, 12)">
                                    Zoom to UCs
                                </button>
                                <a href="${filterUrl}" class="btn btn-sm btn-outline-success">Filter</a>
                            </div>
                        </div>
                    `);
                    gbMarkerLayer.addLayer(marker);
                });
            } else {
                if (indicator) indicator.textContent = 'Level: Union Council / MC Clusters (' + gbClusters.ucs.length + ')';
                gbClusters.ucs.forEach(item => {
                    const icon = L.divIcon({
                        className: '',
                        html: `
                            <div class="gb-cluster-marker" title="${item.name} (${item.district}): ${item.total} Villages">
                                <div class="gb-cluster-bubble gb-cluster-bubble--uc">
                                    <span>${item.total}</span>
                                </div>
                                <div class="gb-cluster-label">${item.name}</div>
                            </div>
                        `,
                        iconSize: [0, 0]
                    });

                    const marker = L.marker([item.lat, item.lng], { icon });
                    const filterUrl = `${gbBaseDirectoryUrl}?sheet=${encodeURIComponent(item.sheet_code)}&q=${encodeURIComponent(item.name)}#directory-section`;
                    const villagesHtml = (item.sample_villages || []).map(v => `<li>${v}</li>`).join('');
                    marker.bindPopup(`
                        <div style="min-width: 240px;">
                            <div class="fw-bold fs-6 mb-0" style="color:#5b21b6;">${item.name}</div>
                            <div class="small text-muted mb-2">Tehsil: ${item.tehsil} &bull; District: ${item.district}</div>
                            <div class="d-flex justify-content-between small border-bottom py-1">
                                <span>Villages in UC:</span>
                                <strong>${item.total}</strong>
                            </div>
                            <div class="d-flex justify-content-between small border-bottom py-1 text-success">
                                <span>VOs Formed:</span>
                                <strong>${item.formed}</strong>
                            </div>
                            <div class="d-flex justify-content-between small py-1 text-warning">
                                <span>No VOs Formed:</span>
                                <strong>${item.not_formed}</strong>
                            </div>
                            ${villagesHtml ? `<div class="mt-2 small"><strong>Villages / VOs:</strong><ul class="ps-3 mb-2 mt-1">${villagesHtml}</ul></div>` : ''}
                            <a href="${filterUrl}" class="btn btn-sm btn-success w-100 mt-1 text-white">Show UC Records in Table</a>
                        </div>
                    `);
                    gbMarkerLayer.addLayer(marker);
                });
            }
        }

        function zoomToGbLocation(lat, lng, targetZoom) {
            if (!gbMap) return;
            gbMapMode = 'auto';
            document.querySelectorAll('.map-level-btn').forEach(b => {
                b.classList.toggle('is-active', b.getAttribute('data-mode') === 'auto');
            });
            gbMap.flyTo([lat, lng], targetZoom, { duration: 0.9 });
        }

        function setGbMapMode(mode, btnEl) {
            gbMapMode = mode;
            document.querySelectorAll('.map-level-btn').forEach(b => b.classList.remove('is-active'));
            if (btnEl) btnEl.classList.add('is-active');
            renderGbMapClusters();
        }

        function resetGbMapView() {
            gbMapMode = 'auto';
            document.querySelectorAll('.map-level-btn').forEach(b => {
                b.classList.toggle('is-active', b.getAttribute('data-mode') === 'auto');
            });
            if (gbMap) {
                gbMap.flyTo([35.65, 74.85], 8, { duration: 0.8 });
            }
        }

        // --- Cascading Dropdown Logic (Division -> District -> Tehsil -> UC) ---
        function filterCascadingDropdowns(clearChildren) {
            const divSel = document.getElementById('globalDivisionSelect');
            const distSel = document.getElementById('globalDistrictSelect');
            const tehSel = document.getElementById('globalTehsilSelect');
            const ucSel = document.getElementById('globalUcSelect');
            if (!divSel || !distSel || !tehSel || !ucSel) return;

            const selectedDiv = divSel.value;
            const selectedDist = distSel.value;
            const selectedTeh = tehSel.value;

            Array.from(distSel.options).forEach(opt => {
                if (!opt.value) return;
                const optDiv = opt.getAttribute('data-division') || '';
                const match = !selectedDiv || optDiv.toLowerCase() === selectedDiv.toLowerCase();
                opt.hidden = !match;
                if (!match && opt.selected && clearChildren) distSel.value = '';
            });

            const activeDist = distSel.value;
            Array.from(tehSel.options).forEach(opt => {
                if (!opt.value) return;
                const optDist = opt.getAttribute('data-district') || '';
                const optDiv = opt.getAttribute('data-division') || '';
                const matchDist = !activeDist || optDist.toLowerCase() === activeDist.toLowerCase();
                const matchDiv = !selectedDiv || optDiv.toLowerCase() === selectedDiv.toLowerCase();
                const match = matchDist && matchDiv;
                opt.hidden = !match;
                if (!match && opt.selected && clearChildren) tehSel.value = '';
            });

            const activeTeh = tehSel.value;
            Array.from(ucSel.options).forEach(opt => {
                if (!opt.value) return;
                const optDist = opt.getAttribute('data-district') || '';
                const optTeh = opt.getAttribute('data-tehsil') || '';
                const optDiv = opt.getAttribute('data-division') || '';
                const matchDist = !activeDist || optDist.toLowerCase() === activeDist.toLowerCase();
                const matchTeh = !activeTeh || optTeh.toLowerCase() === activeTeh.toLowerCase();
                const matchDiv = !selectedDiv || optDiv.toLowerCase() === selectedDiv.toLowerCase();
                const match = matchDist && matchTeh && matchDiv;
                opt.hidden = !match;
                if (!match && opt.selected && clearChildren) ucSel.value = '';
            });
        }

        function onGlobalDivisionChange() {
            document.getElementById('globalDistrictSelect').value = '';
            document.getElementById('globalTehsilSelect').value = '';
            document.getElementById('globalUcSelect').value = '';
            filterCascadingDropdowns(true);
            document.getElementById('globalPageFilterForm').submit();
        }

        function onGlobalDistrictChange() {
            document.getElementById('globalTehsilSelect').value = '';
            document.getElementById('globalUcSelect').value = '';
            filterCascadingDropdowns(true);
            document.getElementById('globalPageFilterForm').submit();
        }

        function onGlobalTehsilChange() {
            document.getElementById('globalUcSelect').value = '';
            filterCascadingDropdowns(true);
            document.getElementById('globalPageFilterForm').submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const mapContainer = document.getElementById('gbOrganizationsMap');
            if (mapContainer && window.L) {
                const initCenter = gbClusters.initial_center || [35.65, 74.85];
                const initZoom = gbClusters.initial_zoom || 8;
                gbMap = L.map('gbOrganizationsMap', {
                    center: initCenter,
                    zoom: initZoom,
                    minZoom: 6,
                    maxZoom: 15,
                    scrollWheelZoom: true
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors &bull; Gilgit-Baltistan ALF Portal',
                    maxZoom: 18
                }).addTo(gbMap);

                gbMarkerLayer = L.layerGroup().addTo(gbMap);
                gbMap.on('zoomend', renderGbMapClusters);
                renderGbMapClusters();
            }

            // Initialize cascading dropdowns without clearing current selections
            filterCascadingDropdowns(false);

            const chartData = @json($chartRows);
            const totalsData = @json($totals);

            // 1. Dynamic Comparison Bar Chart (Districts -> Tehsils -> Union Councils)
            const barCtx = document.getElementById('districtBarChart');
            if (barCtx && window.Chart) {
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.map(r => r.label),
                        datasets: [
                            {
                                label: 'No. of Villages',
                                data: chartData.map(r => r.total_villages),
                                backgroundColor: 'rgba(14, 165, 233, 0.85)',
                                borderColor: '#0284c7',
                                borderWidth: 1,
                                borderRadius: 5
                            },
                            {
                                label: 'VOs Formed',
                                data: chartData.map(r => r.vos_formed),
                                backgroundColor: 'rgba(22, 163, 74, 0.88)',
                                borderColor: '#15803d',
                                borderWidth: 1,
                                borderRadius: 5
                            },
                            {
                                label: 'No VOs Formed',
                                data: chartData.map(r => r.no_vos_formed),
                                backgroundColor: 'rgba(245, 158, 11, 0.88)',
                                borderColor: '#d97706',
                                borderWidth: 1,
                                borderRadius: 5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 16,
                                    font: { weight: '600' }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    afterBody: function (context) {
                                        const idx = context[0].dataIndex;
                                        const row = chartData[idx];
                                        return 'Coverage Rate: ' + row.coverage_pct + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(148, 163, 184, 0.15)' },
                                title: {
                                    display: true,
                                    text: 'Number of Villages',
                                    font: { weight: 'bold' }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            // 2. Overall Coverage Doughnut Chart
            const doughnutCtx = document.getElementById('overallDoughnutChart');
            if (doughnutCtx && window.Chart) {
                new Chart(doughnutCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Villages where VOs Formed', 'Villages where No VOs Formed'],
                        datasets: [{
                            data: [totalsData.vos_formed, totalsData.no_vos_formed],
                            backgroundColor: ['#16a34a', '#f59e0b'],
                            borderColor: ['#ffffff', '#ffffff'],
                            borderWidth: 3,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '66%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 14,
                                    font: { size: 12, weight: '600' }
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
@endpush
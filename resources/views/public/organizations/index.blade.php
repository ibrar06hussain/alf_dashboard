@extends('layouts.public')

@section('title', 'Farmer & Village Organizations - Gilgit-Baltistan')

@push('styles')
<style>
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
</style>
@endpush

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('about_breadcrumb', 'frontend/img/bg-img/5.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">Farmer &amp; Village Organizations (GB)</h2>
                    <p class="text-white-50 mb-2">District-wise Summary &amp; Directory of Agricultural Cooperatives, CBOs, Farmer &amp; Village Organizations across Gilgit-Baltistan</p>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
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
                <h3 class="mb-0 fw-bold" style="color: #1e293b;">List of Farmer's Organizations — Gilgit-Baltistan</h3>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(file_exists(public_path('docs/farmer_organizations_gb.xlsx')))
                    <a href="{{ asset('docs/farmer_organizations_gb.xlsx') }}" download class="btn btn-outline-success btn-sm fw-bold px-3 py-2">
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

        {{-- 1. KPI Summary Cards --}}
        <div class="row g-4 mb-5">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="org-kpi-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-bold small text-uppercase">Total Villages</span>
                        <span class="org-kpi-icon" style="background: #e0f2fe; color: #0284c7;">
                            <i class="fa-solid fa-mountain-city"></i>
                        </span>
                    </div>
                    <h2 class="fw-bold mb-1" style="color: #0f172a;">{{ number_format($totals['total_villages']) }}</h2>
                    <p class="small text-muted mb-0">Across all 10 Districts of GB</p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="org-kpi-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-bold small text-uppercase">VOs Formed</span>
                        <span class="org-kpi-icon" style="background: #dcfce7; color: #16a34a;">
                            <i class="fa-solid fa-people-group"></i>
                        </span>
                    </div>
                    <h2 class="fw-bold mb-1" style="color: #15803d;">{{ number_format($totals['vos_formed']) }}</h2>
                    <p class="small text-muted mb-0">Villages where VOs / Co-ops exist</p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="org-kpi-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-bold small text-uppercase">No VOs Formed</span>
                        <span class="org-kpi-icon" style="background: #fef3c7; color: #d97706;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </span>
                    </div>
                    <h2 class="fw-bold mb-1" style="color: #b45309;">{{ number_format($totals['no_vos_formed']) }}</h2>
                    <p class="small text-muted mb-0">Villages where no VOs formed yet</p>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <div class="org-kpi-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-bold small text-uppercase">VO Coverage Rate</span>
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
        <div class="row g-4 mb-5">
            <div class="col-12 col-lg-8">
                <div class="org-panel h-100">
                    <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <h4><i class="fa-solid fa-chart-column me-2"></i>District-Wise Village Organizations Comparison</h4>
                            <small class="text-white-50">Total Villages vs. VOs Formed vs. No VOs Formed across 10 Districts</small>
                        </div>
                        <span class="badge bg-light text-dark">Interactive Graph</span>
                    </div>
                    <div class="p-4">
                        <div style="position: relative; height: 340px;">
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
                    <div class="p-4 d-flex flex-column justify-content-between">
                        <div style="position: relative; height: 240px;">
                            <canvas id="overallDoughnutChart"></canvas>
                        </div>
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold text-success"><i class="fa-solid fa-circle me-1"></i>Villages where VOs Formed</span>
                                <span class="fw-bold">{{ number_format($totals['vos_formed']) }} ({{ $totals['coverage_pct'] }}%)</span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span class="fw-semibold text-warning"><i class="fa-solid fa-circle me-1"></i>Villages where No VOs Formed</span>
                                <span class="fw-bold">{{ number_format($totals['no_vos_formed']) }} ({{ round(100 - $totals['coverage_pct'], 1) }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Summary Sheet Table (Matching the Summary tab of the Excel Workbook) --}}
        <div class="org-panel mb-5">
            <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4><i class="fa-solid fa-table-list me-2"></i>Detailed Summary of Village Level Organizations (District-Wise)</h4>
                    <small class="text-white-50">Click any District row or "View Sheet" button to filter the village-level directory below</small>
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
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">{{ $row['sheet_code'] }}</span>
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
                                            <div class="progress-thin-bar" style="width: {{ min(100, $row['coverage_pct']) }}%;"></div>
                                        </div>
                                        <span class="small fw-bold" style="min-width: 45px;">{{ $row['coverage_pct'] }}%</span>
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
                            <td colspan="4" class="text-end text-uppercase">Total (Gilgit-Baltistan)</td>
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

        {{-- 4. Village Level Organizations Directory (Workbook Tabs + Search + Table) --}}
        <div id="directory-section" class="org-panel">
            <div class="org-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h4>
                        <i class="fa-solid fa-building-wheat me-2"></i>
                        List of Village Level Organizations
                        @if($activeSheet && isset(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]))
                            — {{ \App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]['district'] }} ({{ $activeSheet }})
                        @elseif($activeDistrict)
                            — {{ $activeDistrict }}
                        @else
                            — All Districts
                        @endif
                    </h4>
                    <small class="text-white-50">Search by Village Name, Cooperative / VO Name, Sub-Division, UC/MC, Focal Person, or Phone Number</small>
                </div>
                <span class="badge bg-white text-dark fw-bold px-3 py-2">
                    Showing {{ $organizations->firstItem() ?? 0 }}–{{ $organizations->lastItem() ?? 0 }} of {{ number_format($organizations->total()) }} Records
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
                <form method="GET" action="{{ route('organizations.index') }}#directory-section" class="row g-3 align-items-end">
                    @if($activeSheet !== '')
                        <input type="hidden" name="sheet" value="{{ $activeSheet }}">
                    @endif

                    <div class="col-12 col-md-5">
                        <label for="orgSearchInput" class="form-label small fw-bold text-uppercase text-muted mb-1">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Search Village, Organization, UC/MC, or Focal Person
                        </label>
                        <input type="text"
                               id="orgSearchInput"
                               name="q"
                               value="{{ $search }}"
                               class="form-control"
                               placeholder="Type village, cooperative/VO name, focal person, or phone...">
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                        <label for="voStatusSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                            Organization Status
                        </label>
                        <select id="voStatusSelect" name="vo_status" class="form-select">
                            <option value="">All Villages (Formed &amp; Not Formed)</option>
                            <option value="formed" @selected($voStatus === 'formed')>VO Formed Only ({{ $totals['vos_formed'] }})</option>
                            <option value="not_formed" @selected($voStatus === 'not_formed')>No VO Formed Yet ({{ $totals['no_vos_formed'] }})</option>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-md-2">
                        <label for="divisionSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">
                            Division
                        </label>
                        <select id="divisionSelect" name="division" class="form-select">
                            <option value="">All Divisions</option>
                            <option value="Gilgit" @selected($activeDivision === 'Gilgit')>Gilgit Division</option>
                            <option value="Baltistan" @selected($activeDivision === 'Baltistan')>Baltistan Division</option>
                            <option value="Diamer" @selected($activeDivision === 'Diamer')>Diamer Division</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-success fw-bold flex-grow-1">
                            <i class="fa-solid fa-filter me-1"></i> Search
                        </button>
                        @if($search !== '' || $voStatus !== '' || $activeDivision !== '' || $activeSheet !== '')
                            <a href="{{ route('organizations.index') }}#directory-section"
                               class="btn btn-outline-secondary"
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
                                        <div class="fw-semibold text-dark">{{ $org->focal_person_name ?: $org->focal_person_raw }}</div>
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
                                        No Village / Farmer Organization records matched your filter criteria.
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const summaryData = @json($summaryRows);
    const totalsData = @json($totals);

    // 1. District Bar Chart
    const barCtx = document.getElementById('districtBarChart');
    if (barCtx && window.Chart) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: summaryData.map(r => r.district),
                datasets: [
                    {
                        label: 'No. of Villages',
                        data: summaryData.map(r => r.total_villages),
                        backgroundColor: 'rgba(14, 165, 233, 0.85)',
                        borderColor: '#0284c7',
                        borderWidth: 1,
                        borderRadius: 5
                    },
                    {
                        label: 'VOs Formed',
                        data: summaryData.map(r => r.vos_formed),
                        backgroundColor: 'rgba(22, 163, 74, 0.88)',
                        borderColor: '#15803d',
                        borderWidth: 1,
                        borderRadius: 5
                    },
                    {
                        label: 'No VOs Formed',
                        data: summaryData.map(r => r.no_vos_formed),
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
                            afterBody: function(context) {
                                const idx = context[0].dataIndex;
                                const row = summaryData[idx];
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


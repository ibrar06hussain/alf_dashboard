@extends('layouts.admin')

@section('title', 'Organizations (GB)')

@push('styles')
<style>
    .org-sheet-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 12px 16px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .org-sheet-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        transition: all 0.15s ease;
    }
    .org-sheet-tab:hover {
        border-color: #2d6a4f;
        color: #1b4332;
        background: #ecfdf5;
    }
    .org-sheet-tab.is-active {
        background: #1b4332;
        border-color: #1b4332;
        color: #ffffff;
    }
    .org-sheet-count {
        background: rgba(15, 23, 42, 0.08);
        padding: 1px 6px;
        border-radius: 999px;
        font-size: 0.72rem;
    }
    .org-sheet-tab.is-active .org-sheet-count {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }
    .alf-custom-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 9998;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .alf-custom-modal-backdrop.is-open {
        display: flex;
    }
    .alf-custom-modal-dialog {
        background: #ffffff;
        border-radius: 14px;
        width: 100%;
        max-width: 680px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
        border: 1px solid #e2e8f0;
    }
    .alf-custom-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        background: linear-gradient(135deg, #1b4332, #2d6a4f);
        color: #ffffff;
        border-top-left-radius: 13px;
        border-top-right-radius: 13px;
    }
    .alf-custom-modal-header h5 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #ffffff;
    }
    .alf-custom-modal-body {
        padding: 22px;
    }
    .alf-custom-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 22px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        border-bottom-left-radius: 13px;
        border-bottom-right-radius: 13px;
    }
</style>
@endpush

@section('content')
@php
    $canManage = auth()->user()?->hasRole('Super Admin');
    $pageStats = [
        [
            'label' => 'Total Villages (10 Districts)',
            'value' => $totals['total_villages'],
            'icon' => 'fa-solid fa-mountain-city',
            'tone' => 'blue',
        ],
        [
            'label' => 'Villages where VOs Formed',
            'value' => $totals['vos_formed'],
            'icon' => 'fa-solid fa-people-group',
            'tone' => 'green',
        ],
        [
            'label' => 'Villages where No VOs Formed',
            'value' => $totals['no_vos_formed'],
            'icon' => 'fa-solid fa-circle-exclamation',
            'tone' => 'amber',
        ],
        [
            'label' => 'Overall GB VO Coverage',
            'value' => $totals['coverage_pct'] . '%',
            'icon' => 'fa-solid fa-chart-pie',
            'tone' => 'purple',
        ],
    ];
@endphp

@include('admin.partials.page-hero', [
    'title' => "Farmer & Village Organizations (GB)",
    'subtitle' => 'District-wise summary, graphical representation, and village-level organization directory across all 10 GB workbooks.',
    'icon' => 'fa-solid fa-people-group',
])

@include('admin.partials.stats', ['stats' => $pageStats])

{{-- Quick Actions Bar --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('organizations.index') }}" target="_blank" class="btn btn-outline-success btn-sm fw-bold">
            <i class="fa-solid fa-up-right-from-square me-1"></i> View Public Website Page
        </a>
        @if(file_exists(public_path('docs/farmer_organizations_gb.xlsx')))
            <a href="{{ asset('docs/farmer_organizations_gb.xlsx') }}" download class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="fa-solid fa-file-excel me-1"></i> Download Source Excel
            </a>
        @endif
    </div>

    @if($canManage)
        <button type="button" class="btn btn-primary fw-bold" onclick="openAddOrgModal()">
            <i class="fa-solid fa-plus me-1"></i> Add New Organization / Village
        </button>
    @endif
</div>

{{-- 1. Graphical Representation Row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card alf-panel h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-chart-column me-2 text-success"></i>District-Wise Villages &amp; VOs Formed Comparison</span>
                <span class="badge bg-success-subtle text-success">10 Districts</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 320px;">
                    <canvas id="adminDistrictBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card alf-panel h-100">
            <div class="card-header">
                <i class="fa-solid fa-chart-pie me-2 text-primary"></i>Overall GB VO Formation Status
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div style="position: relative; height: 220px;">
                    <canvas id="adminOverallDoughnutChart"></canvas>
                </div>
                <div class="border-top pt-3 mt-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold text-success"><i class="fa-solid fa-circle me-1"></i>Villages where VOs Formed</span>
                        <span class="fw-bold">{{ number_format($totals['vos_formed']) }} ({{ $totals['coverage_pct'] }}%)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold text-warning"><i class="fa-solid fa-circle me-1"></i>Villages where No VOs Formed</span>
                        <span class="fw-bold">{{ number_format($totals['no_vos_formed']) }} ({{ round(100 - $totals['coverage_pct'], 1) }}%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2. Detailed Summary Table (Matching the Summary Sheet in the Excel Workbook) --}}
<div class="card alf-panel mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="fa-solid fa-table-list me-2 text-success"></i>Detailed Summary of Village Level Organizations (Summary Sheet)</span>
        @if($activeSheet !== '' || $activeDistrict !== '')
            <a href="{{ route('admin.organizations.index') }}#admin-directory-card" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-rotate-left me-1"></i> Reset District Filter
            </a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">Sr.No.</th>
                        <th>District</th>
                        <th>Workbook Tab</th>
                        <th>Division</th>
                        <th class="text-center">No. of Villages</th>
                        <th class="text-center">No. Villages where VOs Formed</th>
                        <th class="text-center">No. Villages where no VOs Formed</th>
                        <th class="text-center">Coverage %</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summaryRows as $row)
                        @php
                            $isSelected = ($activeSheet === $row['sheet_code']) || ($activeDistrict === $row['district']);
                        @endphp
                        <tr class="{{ $isSelected ? 'table-success' : '' }}">
                            <td class="fw-bold text-muted">{{ $row['sr'] }}</td>
                            <td class="fw-bold">{{ $row['district'] }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $row['sheet_code'] }}</span></td>
                            <td>{{ $row['division'] }}</td>
                            <td class="text-center fw-bold">{{ number_format($row['total_villages']) }}</td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success fw-bold px-2 py-1">
                                    {{ number_format($row['vos_formed']) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($row['no_vos_formed'] > 0)
                                    <span class="badge bg-warning-subtle text-warning-emphasis fw-bold px-2 py-1">
                                        {{ number_format($row['no_vos_formed']) }}
                                    </span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center fw-semibold">{{ $row['coverage_pct'] }}%</td>
                            <td class="text-end">
                                <a href="{{ route('admin.organizations.index', ['sheet' => $row['sheet_code']]) }}#admin-directory-card"
                                   class="btn btn-sm {{ $isSelected ? 'btn-success' : 'btn-outline-primary' }}">
                                    Filter Sheet
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="4" class="text-end text-uppercase">Total</td>
                        <td class="text-center">{{ number_format($totals['total_villages']) }}</td>
                        <td class="text-center text-success">{{ number_format($totals['vos_formed']) }}</td>
                        <td class="text-center text-warning-emphasis">{{ number_format($totals['no_vos_formed']) }}</td>
                        <td class="text-center">{{ $totals['coverage_pct'] }}%</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- 3. Searchable / Filterable Village Level Organizations Directory with Admin CRUD --}}
<div id="admin-directory-card" class="card alf-panel mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <i class="fa-solid fa-building-wheat me-2 text-success"></i>
            <strong>List of Village Level Organizations</strong>
            @if($activeSheet && isset(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]))
                <span class="badge bg-success ms-2">{{ \App\Models\FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]['district'] }} ({{ $activeSheet }})</span>
            @endif
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border">
                Showing {{ $organizations->firstItem() ?? 0 }}–{{ $organizations->lastItem() ?? 0 }} of {{ number_format($organizations->total()) }}
            </span>
            @if($canManage)
                <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="openAddOrgModal()">
                    <i class="fa-solid fa-plus me-1"></i> Add Organization
                </button>
            @endif
        </div>
    </div>

    {{-- Workbook Tabs --}}
    <div class="org-sheet-tabs">
        <a href="{{ route('admin.organizations.index', array_filter(['q' => $search, 'vo_status' => $voStatus])) }}#admin-directory-card"
           class="org-sheet-tab {{ $activeSheet === '' && $activeDistrict === '' ? 'is-active' : '' }}">
            <span>All Workbooks</span>
            <span class="org-sheet-count">{{ $totals['total_villages'] }}</span>
        </a>
        @foreach($summaryRows as $row)
            <a href="{{ route('admin.organizations.index', array_filter(['sheet' => $row['sheet_code'], 'q' => $search, 'vo_status' => $voStatus])) }}#admin-directory-card"
               class="org-sheet-tab {{ $activeSheet === $row['sheet_code'] ? 'is-active' : '' }}">
                <span>{{ $row['sheet_code'] }} ({{ $row['district'] }})</span>
                <span class="org-sheet-count">{{ $row['total_villages'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Search & Filter Form --}}
    <div class="card-body border-bottom bg-light bg-opacity-50">
        <form method="GET" action="{{ route('admin.organizations.index') }}#admin-directory-card" class="row g-2 align-items-end">
            @if($activeSheet !== '')
                <input type="hidden" name="sheet" value="{{ $activeSheet }}">
            @endif

            <div class="col-12 col-md-5">
                <label class="form-label small fw-bold mb-1">Search Records</label>
                <input type="text"
                       name="q"
                       value="{{ $search }}"
                       class="form-control"
                       placeholder="Search village, organization, sub-division, UC/MC, focal person, phone...">
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold mb-1">VO Status</label>
                <select name="vo_status" class="form-select">
                    <option value="">All Records</option>
                    <option value="formed" @selected($voStatus === 'formed')>VO Formed ({{ $totals['vos_formed'] }})</option>
                    <option value="not_formed" @selected($voStatus === 'not_formed')>No VO Formed Yet ({{ $totals['no_vos_formed'] }})</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold mb-1">Division</label>
                <select name="division" class="form-select">
                    <option value="">All Divisions</option>
                    <option value="Gilgit" @selected($activeDivision === 'Gilgit')>Gilgit</option>
                    <option value="Baltistan" @selected($activeDivision === 'Baltistan')>Baltistan</option>
                    <option value="Diamer" @selected($activeDivision === 'Diamer')>Diamer</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                </button>
                @if($search !== '' || $voStatus !== '' || $activeDivision !== '' || $activeSheet !== '')
                    <a href="{{ route('admin.organizations.index') }}#admin-directory-card" class="btn btn-outline-secondary" title="Clear Filters">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Village Level Organizations Table --}}
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 55px;">S#</th>
                        <th>Division</th>
                        <th>District</th>
                        <th>Sub Division</th>
                        <th>UC / MC</th>
                        <th>Name of Village</th>
                        <th>Name of Existing Agri Cooperative / CBO / Farmer Organization / VO</th>
                        <th>Focal Person &amp; Contact</th>
                        @if($canManage)
                            <th class="text-end" style="width: 140px;">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($organizations as $idx => $org)
                        <tr>
                            <td class="text-muted">{{ ($organizations->firstItem() ?? 1) + $idx }}</td>
                            <td>{{ $org->division_name ?: '—' }}</td>
                            <td>
                                <strong>{{ $org->district_name }}</strong>
                                <span class="badge bg-light text-muted border ms-1">{{ $org->sheet_code }}</span>
                            </td>
                            <td>{{ $org->sub_division ?: '—' }}</td>
                            <td>{{ $org->uc_mc ?: '—' }}</td>
                            <td class="fw-bold">{{ $org->village_name }}</td>
                            <td>
                                @if($org->is_vo_formed && $org->organization_name)
                                    <span class="text-success fw-semibold">
                                        <i class="fa-solid fa-circle-check me-1"></i>{{ $org->organization_name }}
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Not Formed Yet</span>
                                @endif
                            </td>
                            <td>
                                @if($org->focal_person_name || $org->focal_person_phone)
                                    <div class="fw-semibold">{{ $org->focal_person_name ?: $org->focal_person_raw }}</div>
                                    @if($org->focal_person_phone)
                                        <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i>{{ $org->focal_person_phone }}</div>
                                    @endif
                                @elseif($org->focal_person_raw)
                                    <span class="small text-muted">{{ $org->focal_person_raw }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @if($canManage)
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit Organization"
                                                data-org="{{ json_encode([
                                                    'id' => $org->id,
                                                    'update_url' => route('admin.organizations.update', $org->id),
                                                    'district_name' => $org->district_name,
                                                    'division_name' => $org->division_name,
                                                    'sub_division' => $org->sub_division,
                                                    'uc_mc' => $org->uc_mc,
                                                    'village_name' => $org->village_name,
                                                    'organization_name' => $org->organization_name,
                                                    'is_vo_formed' => (bool) $org->is_vo_formed,
                                                    'focal_person_name' => $org->focal_person_name,
                                                    'focal_person_phone' => $org->focal_person_phone,
                                                ]) }}"
                                                onclick="openEditOrgModal(this)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        <form method="POST"
                                              action="{{ route('admin.organizations.destroy', $org->id) }}"
                                              onsubmit="return confirm('Are you sure you want to delete the village organization record for {{ addslashes($org->village_name) }} ({{ addslashes($org->district_name) }})?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Organization">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 9 : 8 }}" class="text-center py-4 text-muted">
                                No organization records found matching your search.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($organizations->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center">
            {{ $organizations->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

@if($canManage)
    {{-- ADD ORGANIZATION MODAL --}}
    <div id="addOrgModalBackdrop" class="alf-custom-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="addOrgModalTitle">
        <div class="alf-custom-modal-dialog">
            <div class="alf-custom-modal-header">
                <h5 id="addOrgModalTitle"><i class="fa-solid fa-plus-circle me-2"></i>Add New Village / Farmer Organization</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeAddOrgModal()" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.organizations.store') }}">
                @csrf
                <div class="alf-custom-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">District <span class="text-danger">*</span></label>
                            <select name="district_name" id="add_district_name" class="form-select" required onchange="syncDivisionFromDistrict('add')">
                                <option value="">Select District...</option>
                                @foreach(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP as $code => $meta)
                                    <option value="{{ $meta['district'] }}"
                                            data-division="{{ $meta['division'] }}"
                                            @selected($activeSheet === $code || $activeDistrict === $meta['district'])>
                                        {{ $meta['district'] }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Division</label>
                            <input type="text" name="division_name" id="add_division_name" class="form-control" placeholder="e.g. Gilgit, Baltistan, Diamer">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sub Division / Tehsil</label>
                            <input type="text" name="sub_division" class="form-control" placeholder="e.g. Gilgit, Danyore, Punial...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">UC / MC</label>
                            <input type="text" name="uc_mc" class="form-control" placeholder="Union Council / Municipal Committee">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Name of Village <span class="text-danger">*</span></label>
                            <input type="text" name="village_name" class="form-control" required placeholder="Enter Village Name">
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_vo_formed" value="1" id="add_is_vo_formed" checked onchange="toggleOrgNameInput('add')">
                                <label class="form-check-label fw-bold" for="add_is_vo_formed">
                                    Village Organization (VO / Cooperative / CBO) is Formed
                                </label>
                            </div>
                            <label class="form-label fw-bold">Name of Existing Agri Cooperative / CBO / Farmer Organization / VO</label>
                            <input type="text" name="organization_name" id="add_organization_name" class="form-control" placeholder="Enter Organization / Cooperative Name (leave blank if not formed yet)">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Focal Person Name</label>
                            <input type="text" name="focal_person_name" class="form-control" placeholder="Name of President / Secretary / Focal Person">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Focal Person Contact Number</label>
                            <input type="text" name="focal_person_phone" class="form-control" placeholder="e.g. 0345-1234567">
                        </div>
                    </div>
                </div>
                <div class="alf-custom-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeAddOrgModal()">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Organization
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- EDIT ORGANIZATION MODAL --}}
    <div id="editOrgModalBackdrop" class="alf-custom-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="editOrgModalTitle">
        <div class="alf-custom-modal-dialog">
            <div class="alf-custom-modal-header">
                <h5 id="editOrgModalTitle"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Village / Farmer Organization</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeEditOrgModal()" aria-label="Close"></button>
            </div>
            <form method="POST" id="editOrgForm" action="">
                @csrf
                @method('PUT')
                <div class="alf-custom-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">District <span class="text-danger">*</span></label>
                            <select name="district_name" id="edit_district_name" class="form-select" required onchange="syncDivisionFromDistrict('edit')">
                                @foreach(\App\Models\FarmerOrganization::SHEET_DISTRICT_MAP as $code => $meta)
                                    <option value="{{ $meta['district'] }}" data-division="{{ $meta['division'] }}">
                                        {{ $meta['district'] }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Division</label>
                            <input type="text" name="division_name" id="edit_division_name" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sub Division / Tehsil</label>
                            <input type="text" name="sub_division" id="edit_sub_division" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">UC / MC</label>
                            <input type="text" name="uc_mc" id="edit_uc_mc" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Name of Village <span class="text-danger">*</span></label>
                            <input type="text" name="village_name" id="edit_village_name" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_vo_formed" value="1" id="edit_is_vo_formed" onchange="toggleOrgNameInput('edit')">
                                <label class="form-check-label fw-bold" for="edit_is_vo_formed">
                                    Village Organization (VO / Cooperative / CBO) is Formed
                                </label>
                            </div>
                            <label class="form-label fw-bold">Name of Existing Agri Cooperative / CBO / Farmer Organization / VO</label>
                            <input type="text" name="organization_name" id="edit_organization_name" class="form-control" placeholder="Enter Organization / Cooperative Name">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Focal Person Name</label>
                            <input type="text" name="focal_person_name" id="edit_focal_person_name" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Focal Person Contact Number</label>
                            <input type="text" name="focal_person_phone" id="edit_focal_person_phone" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="alf-custom-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="closeEditOrgModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Update Organization
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    // Modal Helpers (hoisted to document.body so fixed headers never overlap)
    function openAddOrgModal() {
        const el = document.getElementById('addOrgModalBackdrop');
        if (!el) return;
        if (el.parentElement !== document.body) document.body.appendChild(el);
        syncDivisionFromDistrict('add');
        el.classList.add('is-open');
    }

    function closeAddOrgModal() {
        const el = document.getElementById('addOrgModalBackdrop');
        if (el) el.classList.remove('is-open');
    }

    function openEditOrgModal(btn) {
        const raw = btn.getAttribute('data-org');
        if (!raw) return;
        const data = JSON.parse(raw);

        const el = document.getElementById('editOrgModalBackdrop');
        if (!el) return;
        if (el.parentElement !== document.body) document.body.appendChild(el);

        document.getElementById('editOrgForm').action = data.update_url;
        document.getElementById('edit_district_name').value = data.district_name || '';
        document.getElementById('edit_division_name').value = data.division_name || '';
        document.getElementById('edit_sub_division').value = data.sub_division || '';
        document.getElementById('edit_uc_mc').value = data.uc_mc || '';
        document.getElementById('edit_village_name').value = data.village_name || '';
        document.getElementById('edit_organization_name').value = data.organization_name || '';
        document.getElementById('edit_is_vo_formed').checked = !!data.is_vo_formed;
        document.getElementById('edit_focal_person_name').value = data.focal_person_name || '';
        document.getElementById('edit_focal_person_phone').value = data.focal_person_phone || '';

        el.classList.add('is-open');
    }

    function closeEditOrgModal() {
        const el = document.getElementById('editOrgModalBackdrop');
        if (el) el.classList.remove('is-open');
    }

    function syncDivisionFromDistrict(prefix) {
        const sel = document.getElementById(prefix + '_district_name');
        const divInput = document.getElementById(prefix + '_division_name');
        if (!sel || !divInput) return;
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.getAttribute('data-division')) {
            divInput.value = opt.getAttribute('data-division');
        }
    }

    function toggleOrgNameInput(prefix) {
        const chk = document.getElementById(prefix + '_is_vo_formed');
        const inp = document.getElementById(prefix + '_organization_name');
        if (!chk || !inp) return;
        if (!chk.checked) {
            inp.placeholder = 'Not Formed Yet';
        } else {
            inp.placeholder = 'Enter Organization / Cooperative Name';
        }
    }

    // Charts Initialization
    document.addEventListener('DOMContentLoaded', function () {
        const summaryData = @json($summaryRows);
        const totalsData = @json($totals);

        const barCtx = document.getElementById('adminDistrictBarChart');
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
                            borderRadius: 4
                        },
                        {
                            label: 'VOs Formed',
                            data: summaryData.map(r => r.vos_formed),
                            backgroundColor: 'rgba(22, 163, 74, 0.88)',
                            borderRadius: 4
                        },
                        {
                            label: 'No VOs Formed',
                            data: summaryData.map(r => r.no_vos_formed),
                            backgroundColor: 'rgba(245, 158, 11, 0.88)',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        const doughnutCtx = document.getElementById('adminOverallDoughnutChart');
        if (doughnutCtx && window.Chart) {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['VOs Formed', 'No VOs Formed'],
                    datasets: [{
                        data: [totalsData.vos_formed, totalsData.no_vos_formed],
                        backgroundColor: ['#16a34a', '#f59e0b'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    });
</script>
@endpush


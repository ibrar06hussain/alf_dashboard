@extends('layouts.admin')

@section('title', $scheme->title . ' — MIS Dashboard')

@section('content')
@php
    $kpis = $data['kpis'] ?? [];
    $hasItems = (bool) ($data['has_items'] ?? false);
    $itemBreakdown = collect($data['item_breakdown'] ?? []);
    $stageDist = collect($data['stage_distribution'] ?? []);
    $districtRollup = collect($data['district_rollup'] ?? []);
    $fieldBreakdowns = $data['field_breakdowns'] ?? [];
    $gisMarkers = $data['gis_markers'] ?? [];

    $exportUrl = route('admin.scheme-dashboards.export', array_merge(['scheme' => $scheme->id], array_filter($filters)));
    if ($canUnmask && ! $maskPii) {
        $exportUrl .= (str_contains($exportUrl, '?') ? '&' : '?') . 'unmask=1';
    }
@endphp

@include('admin.partials.page-hero', [
    'title' => $scheme->title . ' — MIS Dashboard',
    'subtitle' => ($scheme->department?->name ?? 'Department') . ' · Real-time applications, paid/free itemized breakdown, stock balance, and district rollup scoped to your jurisdiction.',
    'icon' => 'fa-solid fa-chart-pie',
    'actions' => [
        ['label' => 'Applications Queue', 'url' => route('admin.applications.index', ['scheme_id' => $scheme->id]), 'class' => 'btn btn-outline-primary', 'icon' => 'fa-solid fa-folder-open'],
        ['label' => 'Export CSV', 'url' => $exportUrl, 'class' => 'btn btn-success', 'icon' => 'fa-solid fa-file-csv'],
    ],
])

{{-- Filter Bar --}}
<div class="card alf-panel shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.scheme-dashboards.show', $scheme) }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">District</label>
                <select name="district_id" class="form-select form-select-sm">
                    <option value="">All Authorized Districts</option>
                    @foreach($districts as $dist)
                        <option value="{{ $dist->id }}" @selected(($filters['district_id'] ?? null) == $dist->id)>{{ $dist->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Lifecycle Stage</label>
                <select name="stage" class="form-select form-select-sm">
                    <option value="">All Stages</option>
                    @foreach($stages as $stKey => $stLabel)
                        <option value="{{ $stKey }}" @selected(($filters['stage'] ?? null) === $stKey)>{{ $stLabel }}</option>
                    @endforeach
                </select>
            </div>
            @if($hasItems)
                <div class="col-md-2">
                    <label class="form-label small fw-bold mb-1">Selection Type</label>
                    <select name="billing_type" class="form-select form-select-sm">
                        <option value="">All Requests</option>
                        <option value="has_paid" @selected(($filters['billing_type'] ?? null) === 'has_paid')>Includes Paid Items</option>
                        <option value="free_only" @selected(($filters['billing_type'] ?? null) === 'free_only')>Free Items Only (No PSID)</option>
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="App #, CNIC, Challan, PSID">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="fa-solid fa-filter me-1"></i> Filter
                </button>
                <a href="{{ route('admin.scheme-dashboards.show', $scheme) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
        @if($canUnmask)
            <div class="mt-2 pt-2 border-top d-flex justify-content-end align-items-center gap-2 small">
                <span class="text-muted">PII Protection:</span>
                @if($maskPii)
                    <a href="{{ route('admin.scheme-dashboards.show', array_merge(['scheme' => $scheme->id], array_filter($filters), ['unmask' => 1])) }}" class="btn btn-xs btn-outline-warning py-0 px-2">
                        <i class="fa-solid fa-eye me-1"></i> Unmask CNIC &amp; Phone
                    </a>
                @else
                    <a href="{{ route('admin.scheme-dashboards.show', array_merge(['scheme' => $scheme->id], array_filter($filters))) }}" class="btn btn-xs btn-outline-success py-0 px-2">
                        <i class="fa-solid fa-eye-slash me-1"></i> Mask PII
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>

{{-- Primary KPI Row --}}
@include('admin.partials.stats', ['stats' => [
    ['label' => 'Total Applications', 'value' => number_format($kpis['total_applications'] ?? 0), 'icon' => 'fa-solid fa-folder', 'tone' => 'blue'],
    ['label' => 'Approved Cases', 'value' => number_format($kpis['approved_cases'] ?? 0), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'green'],
    ['label' => 'Ready / Disbursed', 'value' => number_format(($kpis['ready_for_disbursement'] ?? 0) + ($kpis['disbursed_cases'] ?? 0)), 'icon' => 'fa-solid fa-truck-ramp-box', 'tone' => 'teal'],
    ['label' => 'Pending Review', 'value' => number_format($kpis['pending_review'] ?? 0), 'icon' => 'fa-solid fa-clock', 'tone' => 'amber'],
]])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Requested Units', 'value' => number_format($kpis['total_demanded_qty'] ?? 0), 'icon' => 'fa-solid fa-seedling', 'tone' => 'slate'],
    ['label' => 'Paid vs Free Units', 'value' => number_format($kpis['paid_qty'] ?? 0) . ' Paid / ' . number_format($kpis['free_qty'] ?? 0) . ' Free', 'icon' => 'fa-solid fa-scale-balanced', 'tone' => 'blue'],
    ['label' => 'Collected Revenue', 'value' => 'Rs. ' . number_format($kpis['collected_amount'] ?? 0), 'icon' => 'fa-solid fa-coins', 'tone' => 'green'],
    ['label' => 'Govt Subsidy Share', 'value' => 'Rs. ' . number_format($kpis['govt_subsidy_amount'] ?? 0), 'icon' => 'fa-solid fa-hand-holding-heart', 'tone' => 'teal'],
]])

{{-- Itemized Paid/Free & Stock Matrix (Auto-generated for checkbox_with_text / multi-item schemes) --}}
@if($hasItems)
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card alf-panel shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="fa-solid fa-list-check text-success me-1"></i> Itemized Demand, Approval, Free vs Paid &amp; Depot Stock</span>
                    <span class="badge bg-light text-dark border">{{ $itemBreakdown->count() }} Item Types</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item / Species</th>
                                    <th class="text-center">Billing</th>
                                    <th class="text-end">Applicants</th>
                                    <th class="text-end">Demanded</th>
                                    <th class="text-end">Approved</th>
                                    <th class="text-end">Disbursed</th>
                                    <th class="text-end">Depot Stock</th>
                                    <th class="text-end">Payable (PKR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($itemBreakdown as $itemRow)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $itemRow['item_label'] }}</div>
                                            <small class="text-muted">{{ $itemRow['unit_label'] }}</small>
                                        </td>
                                        <td class="text-center">
                                            @if($itemRow['is_free'])
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">Free</span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Paid</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($itemRow['applications_count']) }}</td>
                                        <td class="text-end">{{ number_format($itemRow['demanded_qty']) }}</td>
                                        <td class="text-end fw-bold text-primary">{{ number_format($itemRow['approved_qty']) }}</td>
                                        <td class="text-end text-success">{{ number_format($itemRow['disbursed_qty']) }}</td>
                                        <td class="text-end">
                                            @if($itemRow['available_stock'] !== null)
                                                @php $deficit = $itemRow['available_stock'] < $itemRow['pending_disburse_qty']; @endphp
                                                <span class="badge {{ $deficit ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                                    {{ number_format($itemRow['available_stock']) }}
                                                </span>
                                            @else
                                                <span class="text-muted small">Not Linked</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $itemRow['is_free'] ? 'Rs. 0' : 'Rs. ' . number_format($itemRow['farmer_payable_amount']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card alf-panel shadow-sm h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-chart-column text-primary me-1"></i> Itemized Quantities (Demanded vs Approved vs Disbursed)
                </div>
                <div class="card-body">
                    <div id="scheme-items-chart" style="min-height: 270px;"></div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Stage Funnel & District Rollup --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-diagram-project text-info me-1"></i> Lifecycle Stage Breakdown
            </div>
            <div class="card-body">
                <div id="scheme-stages-chart" style="min-height: 250px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-map-location-dot text-success me-1"></i> District-Wise Allocation &amp; Progress
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>District</th>
                                <th class="text-end">Apps</th>
                                <th class="text-end">Approved</th>
                                <th class="text-end">Disbursed</th>
                                <th class="text-end">Demanded Units</th>
                                <th class="text-end">Approved Units</th>
                                <th class="text-end">Quota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($districtRollup as $dRow)
                                <tr>
                                    <td class="fw-semibold">{{ $dRow->district_name }}</td>
                                    <td class="text-end">{{ number_format($dRow->total_applications) }}</td>
                                    <td class="text-end text-primary fw-semibold">{{ number_format($dRow->approved_cases) }}</td>
                                    <td class="text-end text-success">{{ number_format($dRow->disbursed_cases) }}</td>
                                    <td class="text-end">{{ number_format((float) $dRow->demanded_qty) }}</td>
                                    <td class="text-end fw-bold">{{ number_format((float) $dRow->approved_qty) }}</td>
                                    <td class="text-end text-muted">{{ $dRow->quota !== null ? number_format($dRow->quota) : 'Province-Wide' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">No district records for current filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auto-generated Categorical Form Field Charts --}}
@if(!empty($fieldBreakdowns))
    <div class="row g-3 mb-4">
        @foreach($fieldBreakdowns as $fb)
            <div class="col-md-6">
                <div class="card alf-panel shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">{{ $fb['field_label'] }} Distribution</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($fb['counts'] as $cnt)
                                    <tr>
                                        <td>{{ $cnt['option'] }}</td>
                                        <td class="text-end fw-bold">{{ number_format($cnt['total']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Paginated Applications & Item Ledger (AJAX Paginated) --}}
<div class="card alf-panel shadow-sm" data-ajax-pagination id="scheme-ledger-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fa-solid fa-table text-primary me-1"></i> Beneficiary &amp; Itemized Ledger ({{ $applications->total() }})</span>
        <span class="small text-muted">Showing {{ $applications->firstItem() ?? 0 }}–{{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table alf-table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>App No</th>
                        <th>Applicant</th>
                        <th>District</th>
                        <th>Requested / Approved Items</th>
                        <th class="text-end">Challan / PSID</th>
                        <th>Stage</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                        @php
                            $cnicRaw = (string) ($app->applicant?->cnic ?? '');
                            $phoneRaw = (string) ($app->applicant?->phone ?? '');
                            $cnicShown = $maskPii && strlen($cnicRaw) >= 6 ? substr($cnicRaw, 0, 5) . '******' . substr($cnicRaw, -2) : $cnicRaw;
                            $phoneShown = $maskPii && strlen($phoneRaw) >= 6 ? substr($phoneRaw, 0, 4) . '****' . substr($phoneRaw, -2) : $phoneRaw;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.applications.show', $app) }}" class="fw-bold">{{ $app->application_no }}</a>
                                <div class="small text-muted">{{ $app->created_at?->format('d M Y') }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $app->applicant?->full_name ?? 'Applicant' }}</div>
                                <div class="small text-muted font-monospace">{{ $cnicShown }} · {{ $phoneShown }}</div>
                            </td>
                            <td>{{ $app->district?->name ?? '—' }}</td>
                            <td>
                                @if($app->items->isNotEmpty())
                                    @foreach($app->items as $it)
                                        <span class="badge {{ $it->is_free ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }} border me-1 mb-1">
                                            {{ $it->item_label }}: {{ rtrim(rtrim(number_format((float) ($it->approved_qty ?? $it->demanded_qty), 2, '.', ''), '0'), '.') }}
                                            ({{ $it->is_free ? 'Free' : 'Paid' }})
                                        </span>
                                    @endforeach
                                @else
                                    <span>{{ $app->getApprovedQuantity() ?? $app->getDemandedQuantity() }} {{ $app->resolveUnitLabel() }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($app->challan_no)
                                    <div class="fw-semibold">Rs. {{ number_format((float) $app->challan_amount) }}</div>
                                    <div class="small text-muted font-monospace">{{ $app->psid ?: $app->challan_no }}</div>
                                @else
                                    <span class="badge bg-success-subtle text-success">Free (No Challan)</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ \App\Models\Application::translateStatus($app->current_stage ?: $app->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.applications.show', $app) }}" class="btn btn-sm btn-outline-primary">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No applications match your current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white">
        {{ $applications->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin-assets/assets/scripts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;

    @if($hasItems)
    const itemsEl = document.getElementById('scheme-items-chart');
    if (itemsEl) {
        new ApexCharts(itemsEl, {
            chart: { type: 'bar', height: 270, toolbar: { show: false } },
            series: [
                { name: 'Demanded', data: @json($itemBreakdown->pluck('demanded_qty')->values()) },
                { name: 'Approved', data: @json($itemBreakdown->pluck('approved_qty')->values()) },
                { name: 'Disbursed', data: @json($itemBreakdown->pluck('disbursed_qty')->values()) }
            ],
            xaxis: { categories: @json($itemBreakdown->map(fn ($r) => $r['item_label'] . ($r['is_free'] ? ' (Free)' : ' (Paid)'))->values()) },
            colors: ['#2563eb', '#16a34a', '#0d9488'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            dataLabels: { enabled: false }
        }).render();
    }
    @endif

    const stagesEl = document.getElementById('scheme-stages-chart');
    if (stagesEl) {
        const stageLabels = @json($stageDist->pluck('label')->values());
        const stageCounts = @json($stageDist->pluck('total')->values());
        if (stageCounts.length) {
            new ApexCharts(stagesEl, {
                chart: { type: 'donut', height: 260 },
                series: stageCounts,
                labels: stageLabels,
                legend: { position: 'bottom', fontSize: '12px' }
            }).render();
        } else {
            stagesEl.innerHTML = '<div class="text-center text-muted py-5 small">No applications submitted yet.</div>';
        }
    }
});
</script>
@endpush

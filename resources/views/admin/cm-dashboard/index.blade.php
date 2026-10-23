@extends('layouts.admin')

@section('title', 'CM Dashboard')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'CM Dashboard',
    'subtitle' => 'Analytics across departments, districts and schemes.',
    'icon' => 'fa-solid fa-chart-line',
    'actions' => [
        ['label' => 'Excel', 'url' => route('admin.cm-dashboard.export', array_merge($filters, ['format' => 'xlsx'])), 'class' => 'btn btn-success'],
        ['label' => 'CSV', 'url' => route('admin.cm-dashboard.export', array_merge($filters, ['format' => 'csv'])), 'class' => 'btn btn-info'],
        ['label' => 'PDF', 'url' => route('admin.cm-dashboard.export', array_merge($filters, ['format' => 'pdf'])), 'class' => 'btn btn-danger'],
    ],
])

<div class="card alf-panel mb-3">
    <div class="card-header">Filters</div>
    <div class="card-body">
        <form id="cm-filters" class="alf-filters row g-2 mb-0">
            <div class="col-md-2">
                @php
                    $lockDept = auth()->user()->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries']);
                @endphp
                <select name="department_id" class="form-control" @disabled($lockDept)>
                    @unless($lockDept)
                        <option value="">All departments</option>
                    @endunless
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected(($filters['department_id'] ?? null) == $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @if($lockDept)
                    <input type="hidden" name="department_id" value="{{ $filters['department_id'] ?? auth()->user()->department_id }}">
                @endif
            </div>
            <div class="col-md-2">
                <select name="district_id" class="form-control">
                    <option value="">All districts</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected(($filters['district_id'] ?? null) == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="scheme_id" class="form-control">
                    <option value="">All schemes</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected(($filters['scheme_id'] ?? null) == $scheme->id)>{{ $scheme->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\Application::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from_date" class="form-control" value="{{ $filters['from_date'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="to_date" class="form-control" value="{{ $filters['to_date'] ?? '' }}">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

@php
    $cmKpiMeta = [
        'applications' => ['Applications', 'fa-solid fa-folder', 'green'],
        'pending' => ['Pending', 'fa-solid fa-clock', 'amber'],
        'district_verified' => ['District verified', 'fa-solid fa-location-dot', 'blue'],
        'directorate_approved' => ['Approved', 'fa-solid fa-thumbs-up', 'teal'],
        'disbursed' => ['Disbursed', 'fa-solid fa-hand-holding-dollar', 'green'],
        'rejected' => ['Rejected', 'fa-solid fa-ban', 'rose'],
    ];
@endphp
<div class="alf-stats-row mb-4" id="kpi-row">
    <div class="row g-3">
        @foreach($cmKpiMeta as $key => [$label, $icon, $tone])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="alf-stat-card alf-stat-card--{{ $tone }}">
                    <div class="alf-stat-card__icon"><i class="{{ $icon }}" aria-hidden="true"></i></div>
                    <div class="alf-stat-card__body">
                        <div class="alf-stat-card__value" data-kpi="{{ $key }}">0</div>
                        <div class="alf-stat-card__label">{{ $label }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-3">
        <div class="card alf-panel">
            <div class="card-header">By Status</div>
            <div class="card-body"><div id="chart-status"></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card alf-panel">
            <div class="card-header">By Department</div>
            <div class="card-body"><div id="chart-department"></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card alf-panel">
            <div class="card-header">By District</div>
            <div class="card-body"><div id="chart-district"></div></div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card alf-panel">
            <div class="card-header">Monthly Trend</div>
            <div class="card-body"><div id="chart-month"></div></div>
        </div>
    </div>
    <div class="col-12 mb-3">
        <div class="card alf-panel">
            <div class="card-header">District Allocation Heatmap (GB)</div>
            <div class="card-body">
                <div id="gb-heatmap" class="d-flex flex-wrap gap-2"></div>
                <div class="small text-muted mt-3">Colour intensity reflects application density for the selected filters.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin-assets/assets/scripts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const analyticsUrl = @json(route('admin.cm-dashboard.analytics'));
    const exportBase = @json(route('admin.cm-dashboard.export'));
    let charts = {};

    function params() {
        return new URLSearchParams(new FormData(document.getElementById('cm-filters')));
    }

    function syncExportLinks() {
        const q = params().toString();
        document.querySelectorAll('.page-title-actions a').forEach(a => {
            const format = new URL(a.href).searchParams.get('format') || 'xlsx';
            a.href = exportBase + '?' + q + (q ? '&' : '') + 'format=' + format;
        });
    }

    function renderBar(el, labels, series, name) {
        const options = {
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            series: [{ name: name, data: series.map(Number) }],
            xaxis: { categories: labels },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
            dataLabels: { enabled: false },
        };
        if (charts[el]) {
            charts[el].updateOptions(options);
            charts[el].updateSeries(options.series);
            return;
        }
        charts[el] = new ApexCharts(document.querySelector(el), options);
        charts[el].render();
    }

    function renderDonut(el, labels, series) {
        const options = {
            chart: { type: 'donut', height: 320 },
            labels: labels,
            series: series.map(Number),
            legend: { position: 'bottom' },
        };
        if (charts[el]) {
            charts[el].updateOptions(options);
            charts[el].updateSeries(options.series);
            return;
        }
        charts[el] = new ApexCharts(document.querySelector(el), options);
        charts[el].render();
    }

    async function loadAnalytics() {
        syncExportLinks();
        const res = await fetch(analyticsUrl + '?' + params().toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();

        Object.keys(data.totals || {}).forEach(key => {
            const node = document.querySelector('[data-kpi="' + key + '"]');
            if (node) node.textContent = Number(data.totals[key]).toLocaleString();
        });

        renderDonut('#chart-status', data.by_status.labels, data.by_status.series);
        renderBar('#chart-department', data.by_department.labels, data.by_department.series, 'Applications');
        renderBar('#chart-district', data.by_district.labels, data.by_district.series, 'Applications');
        renderBar('#chart-month', data.by_month.labels, data.by_month.series, 'Applications');
        renderHeatmap(data.by_district.labels || [], data.by_district.series || []);
    }

    function renderHeatmap(labels, series) {
        const wrap = document.getElementById('gb-heatmap');
        if (!wrap) return;
        const values = series.map(Number);
        const max = Math.max(1, ...values);
        const districts = @json(\App\Models\District::orderBy('name')->pluck('name'));
        const map = {};
        labels.forEach((label, i) => { map[label] = values[i] || 0; });

        wrap.innerHTML = districts.map(function (name) {
            const count = map[name] || 0;
            const intensity = count / max;
            const bg = 'rgba(40, 167, 69,' + (0.15 + intensity * 0.85).toFixed(2) + ')';
            return '<div style="min-width:140px;flex:1;border-radius:8px;padding:14px 12px;background:' + bg + ';border:1px solid #d9e8d9;">' +
                '<div class="fw-bold">' + name + '</div>' +
                '<div class="h4 mb-0">' + count + '</div>' +
                '<div class="small text-muted">applications</div></div>';
        }).join('');
    }

    document.getElementById('cm-filters').addEventListener('submit', function (e) {
        e.preventDefault();
        loadAnalytics();
    });

    // Wait a tick for apex bundle
    setTimeout(loadAnalytics, 300);
});
</script>
@endpush

@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-arielle-smile text-white">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div>
                Demand vs Available Stock Analysis
                <div class="page-title-subheading">
                    Compare physical warehouse balances against approved beneficiary demand across districts, tehsils, and collection centers.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <a href="{{ route('admin.stock.transfers') }}" class="btn btn-info shadow-sm mr-2">
                <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Balance Stock (Transfer)
            </a>
            <a href="{{ route('admin.stock.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Stock Overview
            </a>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="main-card mb-3 card shadow-sm">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.stock.demand') }}" class="form-row align-items-center">
            @if(!auth()->user()->district_id)
            <div class="col-md-4 my-1">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span></div>
                    <select name="district_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Districts (Provincial View)</option>
                        @foreach($districts as $d)
                            <option value="{{ $d->id }}" {{ $districtId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif
            <div class="col-md-5 my-1">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-solid fa-file-lines"></i></span></div>
                    <select name="scheme_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Schemes</option>
                        @foreach($schemes as $s)
                            <option value="{{ $s->id }}" {{ $schemeId == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3 my-1">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa-solid fa-rotate mr-1"></i> Refresh Metrics</button>
            </div>
        </form>
    </div>
</div>

{{-- Metric Callout Cards --}}
<div class="row">
    <div class="col-md-3">
        <div class="card mb-3 widget-content bg-midnight-bloom text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Approved Cases</div>
                    <div class="widget-subheading opacity-8">Eligible Beneficiaries</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white"><span>{{ number_format($metrics['approved_cases_count']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mb-3 widget-content bg-warning text-dark shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Total Demand Required</div>
                    <div class="widget-subheading text-dark opacity-8">{{ $metrics['pending_cases_count'] }} Awaiting Items</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-dark"><span>{{ number_format($metrics['pending_demand_units']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mb-3 widget-content bg-primary text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Available in Depots</div>
                    <div class="widget-subheading opacity-8">Physical Stock Balance</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white"><span>{{ number_format($metrics['available_stock']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card mb-3 widget-content {{ $metrics['is_deficit'] ? 'bg-danger' : 'bg-success' }} text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">{{ $metrics['is_deficit'] ? 'Deficit (Shortage)' : 'Net Surplus' }}</div>
                    <div class="widget-subheading opacity-8">{{ $metrics['is_deficit'] ? 'Need More Consignments' : 'Demand Covered' }}</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white">
                        <span>{{ $metrics['variance'] >= 0 ? '+' : '' }}{{ number_format($metrics['variance']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stock Sufficiency Gauge and Breakdown --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-chart-simple mr-2 text-primary"></i> Stock vs Demand Sufficiency Index</span>
        <span class="badge {{ $metrics['sufficiency_rate'] >= 100 ? 'badge-success' : 'badge-danger' }} p-2">
            Sufficiency: {{ $metrics['sufficiency_rate'] }}%
        </span>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar {{ $metrics['sufficiency_rate'] >= 100 ? 'bg-success' : ($metrics['sufficiency_rate'] >= 60 ? 'bg-warning' : 'bg-danger') }}" 
                         role="progressbar" 
                         style="width: {{ min(100, $metrics['sufficiency_rate']) }}%;" 
                         aria-valuenow="{{ $metrics['sufficiency_rate'] }}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        {{ $metrics['sufficiency_rate'] }}% Available
                    </div>
                </div>
                <p class="text-muted mb-0 small">
                    @if($metrics['is_deficit'])
                        <i class="fa-solid fa-triangle-exclamation text-danger mr-1"></i>
                        <strong>Urgent Action Required:</strong> Approved beneficiary demand exceeds available physical stock by <strong>{{ number_format(abs($metrics['variance'])) }} units</strong>. Request fresh consignment or reallocate from neighboring surplus depots.
                    @else
                        <i class="fa-solid fa-circle-check text-success mr-1"></i>
                        <strong>Stock Position Healthy:</strong> Depots hold sufficient quantity to fulfill all currently approved and pending beneficiary cases with a surplus of <strong>{{ number_format($metrics['variance']) }} units</strong>.
                    @endif
                </p>
            </div>
            <div class="col-md-4 border-left">
                <div class="pl-2">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Total Received (All Time):</span>
                        <strong>{{ number_format($metrics['total_received']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Distributed to Beneficiaries:</span>
                        <strong class="text-success">{{ number_format($metrics['total_distributed']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span class="text-muted">Wasted / Damaged:</span>
                        <strong class="text-danger">{{ number_format($metrics['total_damaged']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Current Available Balance:</span>
                        <strong class="text-primary font-size-md">{{ number_format($metrics['available_stock']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Center-wise Breakdown Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-warehouse mr-2 text-primary"></i> Collection Centers Stock Breakdown</span>
        <span class="text-muted small">Total Centers: {{ $metrics['centers']->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Center Code & Name</th>
                    <th>Level</th>
                    <th>District</th>
                    <th>Tehsil / Village</th>
                    <th class="text-right">Available Stock</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($metrics['centers'] as $center)
                <tr>
                    <td>
                        <span class="badge badge-light border text-muted mr-1">{{ $center->code }}</span>
                        <strong>{{ $center->name }}</strong>
                    </td>
                    <td>
                        @if($center->level === 'district')
                            <span class="badge badge-primary">District Central</span>
                        @elseif($center->level === 'tehsil')
                            <span class="badge badge-info">Tehsil Depot</span>
                        @else
                            <span class="badge badge-secondary">Village Point</span>
                        @endif
                    </td>
                    <td>{{ $center->district->name ?? 'N/A' }}</td>
                    <td>
                        {{ $center->tehsil->name ?? 'Main' }}
                        @if($center->village_name) <br><small class="text-muted">{{ $center->village_name }}</small> @endif
                    </td>
                    <td class="text-right">
                        <span class="font-weight-bold {{ $center->total_available_stock > 0 ? 'text-success font-size-md' : 'text-danger' }}">
                            {{ number_format($center->total_available_stock) }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.stock.transfers') }}?to_center_id={{ $center->id }}" class="btn btn-xs btn-outline-info" title="Transfer Stock to this Center">
                            <i class="fa-solid fa-arrow-right mr-1"></i> Transfer In
                        </a>
                        <a href="{{ route('admin.stock.transfers') }}?from_center_id={{ $center->id }}" class="btn btn-xs btn-outline-secondary" title="Transfer Stock from this Center">
                            <i class="fa-solid fa-arrow-left mr-1"></i> Transfer Out
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-primary text-white">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div>
                Scheme Stock & Distribution Management
                <div class="page-title-subheading">
                    Monitor schemes items received, physical center balances, inter-depot transfers, and demand satisfaction.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <div class="d-inline-block">
                <a href="{{ route('admin.stock.demand') }}" class="btn btn-outline-primary mr-2 shadow-sm">
                    <i class="fa-solid fa-chart-pie mr-1"></i> Demand vs Stock Analysis
                </a>
                <a href="{{ route('admin.stock.receipts') }}?action=record" class="btn btn-success shadow-sm font-weight-bold">
                    <i class="fa-solid fa-plus-circle mr-1"></i> Record Stock In
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Filter Card (for provincial users) --}}
@if(!auth()->user()->district_id)
<div class="main-card mb-3 card shadow-sm">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.stock.index') }}" class="form-row align-items-center">
            <div class="col-md-4 my-1">
                <label class="sr-only" for="district_id">District</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-solid fa-location-dot"></i></span></div>
                    <select name="district_id" id="district_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Districts (Provincial View)</option>
                        @foreach($districts as $d)
                            <option value="{{ $d->id }}" {{ $districtId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-5 my-1">
                <label class="sr-only" for="scheme_id">Scheme</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-solid fa-file-lines"></i></span></div>
                    <select name="scheme_id" id="scheme_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Schemes</option>
                        @foreach($schemes as $s)
                            <option value="{{ $s->id }}" {{ $schemeId == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-3 my-1">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa-solid fa-filter mr-1"></i> Apply Filter</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Executive Metrics Cards --}}
<div class="row">
    <div class="col-md-6 col-xl-3">
        <div class="card mb-3 widget-content bg-midnight-bloom text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Total Stock Received</div>
                    <div class="widget-subheading opacity-8">Cumulative Consignments</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white font-weight-bold"><span>{{ number_format($metrics['total_received']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card mb-3 widget-content bg-arielle-smile text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Available in Centers</div>
                    <div class="widget-subheading opacity-8">Current Physical Balance</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white font-weight-bold"><span>{{ number_format($metrics['available_stock']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card mb-3 widget-content bg-grow-early text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">Approved Demand</div>
                    <div class="widget-subheading opacity-8">{{ $metrics['approved_cases_count'] }} Approved Cases</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white font-weight-bold"><span>{{ number_format($metrics['pending_demand_units']) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card mb-3 widget-content {{ $metrics['is_deficit'] ? 'bg-danger' : 'bg-success' }} text-white shadow-sm">
            <div class="widget-content-wrapper">
                <div class="widget-content-left">
                    <div class="widget-heading">{{ $metrics['is_deficit'] ? 'Stock Deficit' : 'Stock Surplus' }}</div>
                    <div class="widget-subheading opacity-8">Sufficiency: {{ $metrics['sufficiency_rate'] }}%</div>
                </div>
                <div class="widget-content-right">
                    <div class="widget-numbers text-white font-weight-bold">
                        <span>{{ $metrics['variance'] >= 0 ? '+' : '' }}{{ number_format($metrics['variance']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Sufficiency Progress Card --}}
<div class="main-card mb-4 card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="font-weight-bold mb-0 text-dark">
                <i class="fa-solid fa-gauge-high mr-2 text-primary"></i> Stock Sufficiency Index for Approved Cases
            </h6>
            <span class="badge badge-pill {{ $metrics['sufficiency_rate'] >= 100 ? 'badge-success' : ($metrics['sufficiency_rate'] >= 60 ? 'badge-warning' : 'badge-danger') }} px-3 py-1 font-size-md">
                {{ $metrics['sufficiency_rate'] }}% Available
            </span>
        </div>
        <div class="progress" style="height: 12px;">
            <div class="progress-bar progress-bar-striped {{ $metrics['sufficiency_rate'] >= 100 ? 'bg-success' : ($metrics['sufficiency_rate'] >= 60 ? 'bg-warning' : 'bg-danger') }}" 
                 role="progressbar" 
                 style="width: {{ min(100, $metrics['sufficiency_rate']) }}%;" 
                 aria-valuenow="{{ $metrics['sufficiency_rate'] }}" 
                 aria-valuemin="0" 
                 aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between text-muted small mt-2">
            <span><strong>Available Balance:</strong> {{ number_format($metrics['available_stock']) }} Units</span>
            <span><strong>Pending Beneficiary Requirement:</strong> {{ number_format($metrics['pending_demand_units']) }} Units</span>
            <span><strong>Total Disbursed:</strong> {{ number_format($metrics['total_distributed']) }} Units</span>
            <span><strong>Damaged / Wasted:</strong> {{ number_format($metrics['total_damaged']) }} Units</span>
        </div>
    </div>
</div>

{{-- Quick Links / Management Tabs --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm h-100 border-left-primary">
            <div class="card-body text-center p-3">
                <div class="avatar-icon-wrapper rounded-circle bg-light text-primary p-3 mb-2 mx-auto" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-warehouse fa-lg"></i>
                </div>
                <h6 class="font-weight-bold mb-1">Collection Centers</h6>
                <p class="text-muted small mb-2">District, Tehsil & Village Depots</p>
                <a href="{{ route('admin.stock.centers') }}" class="btn btn-outline-primary btn-sm btn-block">Manage Centers</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100 border-left-success">
            <div class="card-body text-center p-3">
                <div class="avatar-icon-wrapper rounded-circle bg-light text-success p-3 mb-2 mx-auto" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-truck-ramp-box fa-lg"></i>
                </div>
                <h6 class="font-weight-bold mb-1">Stock Receipts</h6>
                <p class="text-muted small mb-2">Incoming Items from Suppliers</p>
                <a href="{{ route('admin.stock.receipts') }}" class="btn btn-outline-success btn-sm btn-block">Stock In Logs</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100 border-left-info">
            <div class="card-body text-center p-3">
                <div class="avatar-icon-wrapper rounded-circle bg-light text-info p-3 mb-2 mx-auto" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-arrow-right-arrow-left fa-lg"></i>
                </div>
                <h6 class="font-weight-bold mb-1">Stock Transfers</h6>
                <p class="text-muted small mb-2">Inter-Center Demand Movements</p>
                <a href="{{ route('admin.stock.transfers') }}" class="btn btn-outline-info btn-sm btn-block">Transfer Stock</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100 border-left-danger">
            <div class="card-body text-center p-3">
                <div class="avatar-icon-wrapper rounded-circle bg-light text-danger p-3 mb-2 mx-auto" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-triangle-exclamation fa-lg"></i>
                </div>
                <h6 class="font-weight-bold mb-1">Damaged Stock</h6>
                <p class="text-muted small mb-2">Wasted Stock & Proof Upload</p>
                <a href="{{ route('admin.stock.damages') }}" class="btn btn-outline-danger btn-sm btn-block">Record Damage</a>
            </div>
        </div>
    </div>
</div>

{{-- Collection Centers Stock Balance Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-warehouse mr-2 text-primary"></i> Collection Centers Stock Status</span>
        <a href="{{ route('admin.stock.centers') }}" class="btn btn-sm btn-light">View All Centers</a>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Center Code & Name</th>
                    <th>Level</th>
                    <th>District / Tehsil</th>
                    <th>Focal Person</th>
                    <th class="text-right">Available Stock</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($metrics['centers']->take(10) as $c)
                <tr>
                    <td>
                        <span class="badge badge-light border text-muted mr-1">{{ $c->code }}</span>
                        <strong>{{ $c->name }}</strong>
                    </td>
                    <td>
                        @if($c->level === 'district')
                            <span class="badge badge-primary">District Main</span>
                        @elseif($c->level === 'tehsil')
                            <span class="badge badge-info">Tehsil Center</span>
                        @else
                            <span class="badge badge-secondary">Village Point</span>
                        @endif
                    </td>
                    <td>
                        {{ $c->district->name ?? 'N/A' }}
                        @if($c->tehsil) <small class="text-muted">/ {{ $c->tehsil->name }}</small> @endif
                    </td>
                    <td>
                        <small class="d-block text-dark">{{ $c->focal_person_name ?? 'Not Assigned' }}</small>
                        <small class="text-muted">{{ $c->focal_person_phone ?? '' }}</small>
                    </td>
                    <td class="text-right">
                        <span class="font-weight-bold {{ $c->total_available_stock > 0 ? 'text-success font-size-md' : 'text-danger' }}">
                            {{ number_format($c->total_available_stock) }}
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.stock.transfers') }}?from_center_id={{ $c->id }}" class="btn btn-xs btn-outline-info" title="Transfer From Here">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                        </a>
                        <a href="{{ route('admin.stock.receipts') }}?center_id={{ $c->id }}" class="btn btn-xs btn-outline-success" title="Add Stock">
                            <i class="fa-solid fa-plus"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No collection centers registered yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Recent Activities Grid --}}
<div class="row">
    {{-- Recent Receipts --}}
    <div class="col-md-6 mb-4">
        <div class="main-card card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-success"><i class="fa-solid fa-truck-ramp-box mr-2"></i> Recent Stock In</span>
                <a href="{{ route('admin.stock.receipts') }}" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentReceipts as $rcv)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#{{ $rcv->receipt_no }}</strong> - {{ $rcv->item->item_name ?? 'Scheme Item' }}
                            <div class="text-muted small">
                                Received at {{ $rcv->center->name ?? 'Center' }} ({{ $rcv->received_date->format('d M Y') }})
                            </div>
                        </div>
                        <span class="badge badge-success badge-pill font-weight-bold font-size-sm">
                            +{{ number_format($rcv->quantity_received) }} {{ $rcv->item->unit ?? 'Units' }}
                        </span>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-3">No stock receipts recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Recent Transfers --}}
    <div class="col-md-6 mb-4">
        <div class="main-card card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="font-weight-bold text-info"><i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> Recent Stock Transfers</span>
                <a href="{{ route('admin.stock.transfers') }}" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentTransfers as $trf)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>#{{ $trf->transfer_no }}</strong>: {{ $trf->fromCenter->name ?? 'Source' }} &rarr; {{ $trf->toCenter->name ?? 'Destination' }}
                            <div class="text-muted small text-truncate" style="max-width: 320px;" title="{{ $trf->demand_justification }}">
                                <i class="fa-solid fa-circle-info text-info mr-1"></i> {{ $trf->demand_justification }}
                            </div>
                        </div>
                        <span class="badge badge-info badge-pill font-weight-bold font-size-sm">
                            {{ number_format($trf->quantity) }} {{ $trf->item->unit ?? 'Units' }}
                        </span>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-3">No transfers recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection

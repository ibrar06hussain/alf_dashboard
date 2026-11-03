@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-info text-white shadow-sm">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
            <div>
                Scheme Stock Disbursement (Stock Out)
                <div class="page-title-subheading">
                    Disburse approved scheme commodities to verified beneficiaries and record physical inventory handovers from collection centers.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <div class="d-inline-block">
                <a href="{{ route('admin.stock.disbursements', ['tab' => 'eligible']) }}" class="btn btn-outline-success font-weight-bold mr-2 shadow-sm">
                    <i class="fa-solid fa-user-check mr-1"></i> Eligible Beneficiaries
                    <span class="badge badge-success ml-1">{{ $eligibleCount }}</span>
                </a>
                <a href="{{ route('admin.stock.disbursements', ['tab' => 'history']) }}" class="btn btn-outline-info font-weight-bold shadow-sm">
                    <i class="fa-solid fa-clock-rotate-left mr-1"></i> Ledger / History
                    <span class="badge badge-info ml-1">{{ $historyCount }}</span>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Navigation Tabs --}}
<ul class="body-tabs body-tabs-layout tabs-animated body-tabs-animated nav mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'eligible' ? 'active' : '' }}" href="{{ route('admin.stock.disbursements', array_merge(request()->query(), ['tab' => 'eligible'])) }}">
            <i class="fa-solid fa-clipboard-check mr-2"></i>
            <span>Eligible for Disbursement (Challans Verified)</span>
            <span class="badge badge-pill badge-success ml-2">{{ $eligibleCount }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'history' ? 'active' : '' }}" href="{{ route('admin.stock.disbursements', array_merge(request()->query(), ['tab' => 'history'])) }}">
            <i class="fa-solid fa-file-invoice mr-2"></i>
            <span>Disbursement Ledger & History</span>
            <span class="badge badge-pill badge-secondary ml-2">{{ $historyCount }}</span>
        </a>
    </li>
</ul>

{{-- Filters Card --}}
<div class="main-card mb-3 card shadow-sm">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.stock.disbursements') }}" class="form-row align-items-center">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="col-md-4 my-1">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span></div>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, CNIC, app #, or challan #" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3 my-1">
                <select name="scheme_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All Schemes</option>
                    @foreach($schemes as $s)
                        <option value="{{ $s->id }}" {{ request('scheme_id') == $s->id ? 'selected' : '' }}>{{ $s->title }}</option>
                    @endforeach
                </select>
            </div>
            @if($tab === 'history')
            <div class="col-md-3 my-1">
                <select name="center_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All Collection Depots</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" {{ request('center_id') == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col my-1 text-right">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter mr-1"></i> Filter</button>
                <a href="{{ route('admin.stock.disbursements', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left mr-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

@if($tab === 'eligible')
{{-- TAB 1: ELIGIBLE APPLICANTS AWAITING DISBURSEMENT --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-user-check mr-2 text-success"></i> Eligible Beneficiaries Ready for Stock Handover ({{ $eligibleApplications->total() }})
        </span>
        <span class="badge badge-light border text-muted">
            <i class="fa-solid fa-shield-halved mr-1 text-success"></i> Payment / Challan Verified
        </span>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>App Tracking No</th>
                    <th>Beneficiary Details</th>
                    <th>Scheme & Commodity</th>
                    <th>Approved Quota</th>
                    <th>Payment / Challan Status</th>
                    <th class="text-right">Remaining to Disburse</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($eligibleApplications as $app)
                @php
                    $quota = $app->allocated_quantity ?: ($app->getBagsRequired() ?: 1);
                    $remaining = $app->getRemainingDisbursementQuantity();
                    $schemeStockItem = $app->scheme?->stockItems?->first();
                    $itemUnit = $schemeStockItem?->unit ?? 'Bags (50kg)';
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.applications.show', $app) }}" class="font-weight-bold text-primary" title="View Full Application Dossier">
                            #{{ $app->application_no }}
                        </a>
                        <div class="small text-muted">{{ $app->created_at->format('d M Y') }}</div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $app->applicant->name ?? 'N/A' }}</div>
                        <div class="small text-muted">
                            <span class="mr-2"><i class="fa-solid fa-id-card text-muted mr-1"></i> {{ $app->applicant->cnic ?? 'N/A' }}</span>
                            @if($app->applicant?->phone)
                                <span><i class="fa-solid fa-phone text-muted mr-1"></i> {{ $app->applicant->phone }}</span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            <i class="fa-solid fa-location-dot text-danger mr-1"></i> {{ $app->district->name ?? '' }}
                        </div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $app->scheme->title ?? 'Government Subsidy Scheme' }}</div>
                        <div class="small text-info">
                            <i class="fa-solid fa-box mr-1"></i> {{ $schemeStockItem->item_name ?? 'Scheme Subsidy Item' }}
                        </div>
                    </td>
                    <td>
                        <div><strong class="text-dark">Approved: {{ $app->getApprovedQuantity() ?? $quota }} {{ $itemUnit }}</strong></div>
                        <div class="small text-muted">Demanded: {{ $app->getDemandedQuantity() }} {{ $itemUnit }}</div>
                        @if($app->distributed_quantity > 0)
                            <div class="small text-success">
                                <i class="fa-solid fa-check mr-1"></i> Disbursed: {{ (float) $app->distributed_quantity }} {{ $itemUnit }}
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($app->challan_no)
                            <span class="badge badge-success px-2 py-1 mb-1 font-size-xs">
                                <i class="fa-solid fa-check-circle mr-1"></i> Paid & Verified
                            </span>
                            <div class="small font-weight-bold text-dark">Challan #{{ $app->challan_no }}</div>
                            @if($app->challan_amount)
                                <div class="small text-muted">Rs. {{ number_format($app->challan_amount) }}</div>
                            @endif
                        @else
                            <span class="badge badge-info px-2 py-1 font-size-xs">
                                <i class="fa-solid fa-circle-check mr-1"></i> Approved Beneficiary
                            </span>
                        @endif
                    </td>
                    <td class="text-right">
                        <span class="badge badge-warning font-size-md px-2 py-1 font-weight-bold text-dark">
                            {{ $remaining }} {{ $itemUnit }}
                        </span>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm px-3"
                                onclick="openDisburseModal({
                                    id: {{ $app->id }},
                                    app_no: '{{ $app->application_no }}',
                                    name: '{{ addslashes($app->applicant->name ?? '') }}',
                                    cnic: '{{ $app->applicant->cnic ?? '' }}',
                                    phone: '{{ $app->applicant->phone ?? '' }}',
                                    scheme_id: {{ $app->scheme_id }},
                                    scheme_title: '{{ addslashes($app->scheme->title ?? '') }}',
                                    item_id: {{ $schemeStockItem->id ?? 0 }},
                                    item_name: '{{ addslashes($schemeStockItem->item_name ?? '') }}',
                                    unit: '{{ addslashes($itemUnit) }}',
                                    demanded_qty: {{ $app->getDemandedQuantity() }},
                                    approved_qty: {{ $app->getApprovedQuantity() ?? $quota }},
                                    remaining: {{ $remaining }},
                                    challan_no: '{{ $app->challan_no ?? '' }}',
                                    district_id: {{ $app->district_id }}
                                })">
                            <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Disburse Stock
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="py-4">
                            <div class="avatar-icon-wrapper rounded-circle bg-light text-success p-3 mb-3 mx-auto" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-check-double fa-2x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-1">No Pending Disbursements</h5>
                            <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                                All approved applicants with verified payment challans in your assigned district have received their stock allocations, or no new verified cases are pending.
                            </p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $eligibleApplications->firstItem() ?? 0 }} to {{ $eligibleApplications->lastItem() ?? 0 }} of {{ $eligibleApplications->total() }}</span>
        {{ $eligibleApplications->links() }}
    </div>
</div>

@else
{{-- TAB 2: DISBURSEMENT LEDGER & HISTORY --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-file-invoice mr-2 text-info"></i> Stock Out / Handover Ledger ({{ $disbursements->total() }})
        </span>
        <a href="{{ route('admin.stock.disbursements', ['tab' => 'eligible']) }}" class="btn btn-success btn-sm shadow-sm font-weight-bold px-3">
            <i class="fa-solid fa-plus-circle mr-1"></i> New Disbursement
        </a>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Voucher No</th>
                    <th>Beneficiary / Recipient</th>
                    <th>Scheme Item</th>
                    <th>Dispensing Depot / Center</th>
                    <th class="text-right">Quantity Disbursed</th>
                    <th>Date Disbursed</th>
                    <th>Disbursed By</th>
                    <th class="text-center">Proof / Slip</th>
                    <th class="text-center">Action / Audit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($disbursements as $d)
                @php
                    $canEditDisb = $d->canUserEdit(auth()->user());
                    $minsLeft = $d->editMinutesRemaining();
                    $hasAudit = $d->editLogs->isNotEmpty();
                @endphp
                <tr>
                    <td>
                        <span class="font-weight-bold text-primary">#{{ $d->disbursement_no }}</span>
                        <div class="small">
                            <a href="{{ route('admin.applications.show', $d->application_id) }}" class="text-muted">
                                App #{{ $d->application->application_no ?? $d->application_id }}
                            </a>
                        </div>
                        @if($hasAudit)
                            <div class="mt-1">
                                <span class="badge badge-warning text-dark font-weight-normal" style="font-size: 10px;" title="This disbursement was edited">
                                    <i class="fa-solid fa-clock-rotate-left mr-1"></i> Edited ({{ $d->editLogs->count() }})
                                </span>
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $d->recipient_name }}</div>
                        <div class="small text-muted">
                            <i class="fa-solid fa-id-card text-muted mr-1"></i> {{ $d->recipient_cnic }}
                            @if(!$d->is_original_applicant)
                                <span class="badge badge-warning ml-1">Representative: {{ $d->representative_relationship ?? 'Authorized' }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <strong>{{ $d->item->item_name ?? 'Scheme Item' }}</strong>
                        <div class="small text-muted">{{ $d->item->scheme->title ?? '' }}</div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $d->center->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $d->center->district->name ?? '' }} ({{ ucfirst($d->center->level ?? 'depot') }})</small>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-danger font-size-md px-2 py-1">
                            -{{ number_format($d->quantity_disbursed) }} {{ $d->item->unit ?? 'Units' }}
                        </span>
                    </td>
                    <td>
                        <i class="fa-solid fa-calendar-day text-muted mr-1"></i> {{ $d->disbursement_date->format('d M Y') }}
                    </td>
                    <td>
                        <small class="d-block font-weight-bold">{{ $d->disbursedBy->name ?? 'Officer' }}</small>
                    </td>
                    <td class="text-center">
                        @if($d->proof_attachment_path)
                            <a href="{{ asset('storage/' . $d->proof_attachment_path) }}" target="_blank" class="btn btn-xs btn-outline-info" title="View Handover Proof Voucher">
                                <i class="fa-solid fa-paperclip mr-1"></i> Proof
                            </a>
                        @else
                            <span class="text-muted small">None</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        <a href="{{ route('admin.stock.disbursements.voucher', $d) }}" target="_blank" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold px-2" title="Print Official Handover Slip">
                            <i class="fa-solid fa-print mr-1"></i> Gate Pass
                        </a>

                        @if($canEditDisb)
                            <button type="button" class="btn btn-sm btn-outline-warning font-weight-bold shadow-sm ml-1"
                                    onclick="openEditDisbursementModal({{ json_encode([
                                        'id' => $d->id,
                                        'disbursement_no' => $d->disbursement_no,
                                        'application_id' => $d->application_id,
                                        'application_no' => $d->application->application_no ?? $d->application_id,
                                        'center_id' => $d->collection_center_id,
                                        'item_id' => $d->scheme_stock_item_id,
                                        'quantity' => (float) $d->quantity_disbursed,
                                        'recipient_name' => $d->recipient_name,
                                        'recipient_cnic' => $d->recipient_cnic,
                                        'recipient_phone' => $d->recipient_phone,
                                        'is_original' => (bool) $d->is_original_applicant,
                                        'representative' => $d->representative_relationship,
                                        'challan' => $d->challan_no,
                                        'date' => $d->disbursement_date->format('Y-m-d'),
                                        'notes' => $d->notes,
                                        'update_url' => route('admin.stock.disbursements.update', $d),
                                        'mins_left' => $minsLeft,
                                        'is_super_admin' => auth()->user()->hasRole('Super Admin'),
                                    ]) }})"
                                    title="{{ auth()->user()->hasRole('Super Admin') ? 'Admin Edit Stock Out' : 'User Edit ('.$minsLeft.' mins left in 10-min grace window)' }}">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                @if(!auth()->user()->hasRole('Super Admin'))
                                    <span class="badge badge-light border ml-1">{{ $minsLeft }}m</span>
                                @endif
                            </button>
                        @else
                            <span class="badge badge-light text-muted border ml-1" title="Edit window expired (>10 mins). Only Super Admin can modify.">
                                <i class="fa-solid fa-lock mr-1"></i> Locked
                            </span>
                        @endif

                        @if($hasAudit)
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-1"
                                    onclick="showDisbursementAuditModal('Disbursement Voucher #{{ $d->disbursement_no }}', {{ json_encode($d->editLogs->map(function($l) {
                                        return [
                                            'date' => $l->created_at->format('d M Y H:i:s'),
                                            'user' => $l->user->name ?? 'System',
                                            'role' => $l->user_role ?? 'Officer',
                                            'justification' => $l->justification,
                                            'changed_fields' => $l->changed_fields,
                                        ];
                                    })) }})"
                                    title="View Audit Trail of Edits & Justifications">
                                <i class="fa-solid fa-history"></i>
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5">
                        <div class="py-4">
                            <div class="avatar-icon-wrapper rounded-circle bg-light text-muted p-3 mb-3 mx-auto" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-box-open fa-2x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-1">No Disbursements Recorded Yet</h5>
                            <p class="text-muted small mb-3" style="max-width: 480px; margin: 0 auto;">
                                When verified farmers collect their scheme allocations at your collection centers, register the handover to deduct inventory and issue official gate passes.
                            </p>
                            <a href="{{ route('admin.stock.disbursements', ['tab' => 'eligible']) }}" class="btn btn-success btn-lg shadow font-weight-bold px-4">
                                <i class="fa-solid fa-user-check mr-2"></i> View Eligible Beneficiaries
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $disbursements->firstItem() ?? 0 }} to {{ $disbursements->lastItem() ?? 0 }} of {{ $disbursements->total() }}</span>
        {{ $disbursements->links() }}
    </div>
</div>
@endif

@endsection

@push('modals')
{{-- DISBURSE STOCK MODAL --}}
<div class="modal fade" id="createDisbursementModal" tabindex="-1" role="dialog" aria-labelledby="createDisbursementModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="{{ route('admin.stock.disbursements.store') }}" enctype="multipart/form-data" id="disbursementForm">
                @csrf
                <input type="hidden" name="application_id" id="modal_app_id" value="">
                <input type="hidden" name="scheme_stock_item_id" id="modal_item_id" value="">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="createDisbursementModalTitle">
                        <i class="fa-solid fa-hand-holding-dollar mr-2"></i> Record Scheme Stock Disbursement
                    </h5>
                    <button type="button" class="close text-white" onclick="closeDisburseModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    {{-- Beneficiary Summary Card --}}
                    <div class="card bg-light border-0 mb-3 shadow-none">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <div class="text-muted small text-uppercase font-weight-bold">Beneficiary Information</div>
                                    <h5 class="font-weight-bold text-dark mb-1" id="disp_beneficiary_name">-</h5>
                                    <div class="small text-muted">
                                        <span class="mr-3"><i class="fa-solid fa-id-card mr-1"></i> <span id="disp_beneficiary_cnic">-</span></span>
                                        <span><i class="fa-solid fa-file-lines mr-1"></i> <span id="disp_app_no">-</span></span>
                                    </div>
                                    <div class="small text-success mt-1" id="disp_challan_box">
                                        <i class="fa-solid fa-check-circle mr-1"></i> Paid Challan: <strong id="disp_challan_no">-</strong>
                                    </div>
                                </div>
                                <div class="col-md-5 text-md-right mt-2 mt-md-0">
                                    <div class="text-muted small text-uppercase font-weight-bold">Eligible Commodity</div>
                                    <div class="font-weight-bold text-primary" id="disp_item_name">-</div>
                                    <div class="small text-muted" id="disp_scheme_title">-</div>
                                    <div class="mt-1">
                                        <span class="badge badge-warning px-2 py-1 font-size-sm">
                                            Remaining Quota: <strong id="disp_remaining_quota">0</strong> <span id="disp_unit">Units</span>
                                        </span>
                                    </div>
                                    <div class="small text-muted mt-1" id="disp_quantities_summary"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Form Row 1: Depot and Quantity --}}
                    <div class="form-row">
                        <div class="col-md-7 form-group">
                            <label class="font-weight-bold text-dark">Dispensing Collection Depot / Center *</label>
                            <select name="collection_center_id" id="modal_center_id" class="form-control" required onchange="updateDepotStockNotice()">
                                <option value="">Select Dispensing Depot</option>
                                @foreach($centers as $c)
                                    @php
                                        $totalAvailable = (float) $c->inventories->sum('current_balance');
                                    @endphp
                                    <option value="{{ $c->id }}" data-stock="{{ $totalAvailable }}">
                                        {{ $c->name }} ({{ ucfirst($c->level) }} - {{ $c->code }}) — Available: {{ number_format($totalAvailable) }} Units
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted" id="depotStockNotice">Select the depot where physical stock is being handed over.</small>
                        </div>
                        <div class="col-md-5 form-group">
                            <label class="font-weight-bold text-dark">Quantity to Disburse *</label>
                            <div class="input-group">
                                <input type="number" step="any" min="0.01" name="quantity_disbursed" id="modal_quantity" class="form-control form-control-lg font-weight-bold text-success" required>
                                <div class="input-group-append">
                                    <span class="input-group-text font-weight-bold bg-light" id="modal_unit_label">Units</span>
                                </div>
                            </div>
                            <small class="text-muted">Cannot exceed remaining approved quota.</small>
                        </div>
                    </div>

                    {{-- Form Row 2: Recipient Type (Applicant vs Representative) --}}
                    <div class="form-group border-top pt-3">
                        <label class="font-weight-bold text-dark d-block">Handover Recipient</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="recip_self" name="is_original_applicant" value="1" class="custom-control-input" checked onchange="toggleRecipientFields()">
                            <label class="custom-control-label font-weight-bold" for="recip_self">Issued directly to Applicant</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="recip_rep" name="is_original_applicant" value="0" class="custom-control-input" onchange="toggleRecipientFields()">
                            <label class="custom-control-label font-weight-bold" for="recip_rep">Issued to Authorized Representative</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient Full Name *</label>
                            <input type="text" name="recipient_name" id="modal_recip_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient CNIC (13 Digits) *</label>
                            <input type="text" name="recipient_cnic" id="modal_recip_cnic" class="form-control" placeholder="71501-XXXXXXX-X" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient Phone</label>
                            <input type="text" name="recipient_phone" id="modal_recip_phone" class="form-control" placeholder="03XXXXXXXXX">
                        </div>
                    </div>

                    <div class="form-row" id="rep_fields" style="display: none;">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Relationship with Applicant</label>
                            <input type="text" name="representative_relationship" id="modal_rep_rel" class="form-control" placeholder="e.g. Son, Brother, Father, Village Head">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Verified Paid Challan Reference</label>
                            <input type="text" name="challan_no" id="modal_challan_input" class="form-control" placeholder="e.g. CHL-202610-0012">
                        </div>
                    </div>

                    {{-- Form Row 3: Proof, Date and Remarks --}}
                    <div class="form-row border-top pt-3">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Disbursement Date *</label>
                            <input type="date" name="disbursement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Handover Proof Attachment</label>
                            <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">Signed receiving sheet, challan stamped receipt, or photo of farmer receiving stock.</small>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Remarks / Verification Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Condition of bags, batch numbers, focal person notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeDisburseModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-check-circle mr-1"></i> Confirm & Disburse Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Disbursement Modal --}}
<div class="modal fade" id="editDisbursementModal" tabindex="-1" role="dialog" aria-labelledby="editDisbursementModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form id="editDisbursementForm" method="POST" action="" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold" id="editDisbursementModalTitle">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Edit Stock Out Voucher (<span id="editDisbNoLabel"></span>)
                    </h5>
                    <button type="button" class="close" onclick="closeEditDisbursementModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div id="editDisbGraceAlert" class="alert alert-info py-2 small mb-3">
                        <i class="fa-solid fa-clock mr-1"></i> <strong id="editDisbWindowText">10-Minute Edit Window Active</strong>
                    </div>

                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="fa-solid fa-file-contract mr-1 text-primary"></i> Linked to Application: <strong class="text-dark" id="edit_disb_app_no"></strong>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Collection Center / Handover Depot *</label>
                            <select name="collection_center_id" id="edit_disb_center_id" class="form-control" required>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} ({{ $c->district->name ?? '' }} - {{ ucfirst($c->level ?? 'depot') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" id="edit_disb_item_id" class="form-control" required>
                                @foreach($items as $it)
                                    <option value="{{ $it->id }}">
                                        {{ $it->item_name }} ({{ $it->unit }}) - {{ $it->scheme->title ?? 'General' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Quantity Disbursed (Units/Bags) *</label>
                            <input type="number" step="0.01" min="0.01" name="quantity_disbursed" id="edit_disb_qty" class="form-control" required>
                            <small class="text-muted">Warehouse balance & beneficiary status will automatically synchronize.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Disbursement Date *</label>
                            <input type="date" name="disbursement_date" id="edit_disb_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient Name *</label>
                            <input type="text" name="recipient_name" id="edit_disb_recip_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient CNIC *</label>
                            <input type="text" name="recipient_cnic" id="edit_disb_recip_cnic" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Recipient Phone</label>
                            <input type="text" name="recipient_phone" id="edit_disb_recip_phone" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Representative Relationship (if not applicant)</label>
                            <input type="text" name="representative_relationship" id="edit_disb_representative" class="form-control" placeholder="Self, Son, Brother, etc.">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Paid Challan Reference</label>
                            <input type="text" name="challan_no" id="edit_disb_challan" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Replace Handover Proof Voucher (Optional)</label>
                        <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">Leave empty to keep existing proof document.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Remarks / Verification Notes</label>
                        <textarea name="notes" id="edit_disb_notes" rows="2" class="form-control"></textarea>
                    </div>

                    {{-- MANDATORY JUSTIFICATION --}}
                    <div class="form-group p-3 border border-warning rounded bg-light mb-0">
                        <label class="font-weight-bold text-danger">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Mandatory Justification for Change *
                        </label>
                        <textarea name="justification" id="edit_disb_justification" rows="2" class="form-control" required minlength="5" placeholder="Specify why this disbursement entry is being edited (e.g., corrected quantity count, updated representative CNIC per identification card)..."></textarea>
                        <small class="text-muted">Every edit, old and new values, and this justification are permanently preserved in the audit log.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeEditDisbursementModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-save mr-1"></i> Save Changes & Recalculate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Disbursement Audit Trail Modal --}}
<div class="modal fade" id="disbursementAuditModal" tabindex="-1" role="dialog" aria-labelledby="disbursementAuditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="disbursementAuditModalTitle">
                    <i class="fa-solid fa-history mr-2"></i> Audit Trail & Change History
                </h5>
                <button type="button" class="close text-white" onclick="closeDisbursementAuditModal()" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <h6 class="font-weight-bold text-primary mb-3" id="disbursementAuditModalSubject"></h6>
                <div id="disbursementAuditModalBody" style="max-height: 420px; overflow-y: auto;">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" onclick="closeDisbursementAuditModal()" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
var currentBeneficiary = {};

function openDisburseModal(data) {
    currentBeneficiary = data;

    document.getElementById('modal_app_id').value = data.id;
    document.getElementById('modal_item_id').value = data.item_id;

    document.getElementById('disp_beneficiary_name').innerText = data.name || 'Beneficiary';
    document.getElementById('disp_beneficiary_cnic').innerText = data.cnic || '-';
    document.getElementById('disp_app_no').innerText = '#' + data.app_no;
    document.getElementById('disp_item_name').innerText = data.item_name || 'Scheme Commodity';
    document.getElementById('disp_scheme_title').innerText = data.scheme_title || '';
    document.getElementById('disp_remaining_quota').innerText = data.remaining || '0';
    document.getElementById('disp_unit').innerText = data.unit || 'Units';
    document.getElementById('modal_unit_label').innerText = data.unit || 'Units';

    var qSummary = document.getElementById('disp_quantities_summary');
    if (qSummary) {
        qSummary.innerHTML = '<span class="text-muted">Demanded: <strong>' + (data.demanded_qty || data.remaining) + '</strong> | Approved: <strong>' + (data.approved_qty || data.remaining) + '</strong> ' + (data.unit || '') + '</span>';
    }

    var qtyInput = document.getElementById('modal_quantity');
    qtyInput.value = data.remaining;
    qtyInput.max = data.remaining;

    if (data.challan_no) {
        document.getElementById('disp_challan_no').innerText = data.challan_no;
        document.getElementById('modal_challan_input').value = data.challan_no;
        document.getElementById('disp_challan_box').style.display = 'block';
    } else {
        document.getElementById('disp_challan_box').style.display = 'none';
        document.getElementById('modal_challan_input').value = '';
    }

    // Default to applicant themselves
    document.getElementById('recip_self').checked = true;
    toggleRecipientFields();

    // Open modal with JS fallback
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createDisbursementModal').modal('show');
    } else {
        var m = document.getElementById('createDisbursementModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.getElementById('disbModalBackdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'disbModalBackdrop';
                backdrop.onclick = closeDisburseModal;
                document.body.appendChild(backdrop);
            }
        }
    }
}

function closeDisburseModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createDisbursementModal').modal('hide');
    } else {
        var m = document.getElementById('createDisbursementModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
            var backdrop = document.getElementById('disbModalBackdrop');
            if (backdrop) backdrop.remove();
        }
    }
}

function toggleRecipientFields() {
    var isSelf = document.getElementById('recip_self').checked;
    var repFields = document.getElementById('rep_fields');

    if (isSelf) {
        document.getElementById('modal_recip_name').value = currentBeneficiary.name || '';
        document.getElementById('modal_recip_cnic').value = currentBeneficiary.cnic || '';
        document.getElementById('modal_recip_phone').value = currentBeneficiary.phone || '';
        if (repFields) repFields.style.display = 'none';
    } else {
        document.getElementById('modal_recip_name').value = '';
        document.getElementById('modal_recip_cnic').value = '';
        document.getElementById('modal_recip_phone').value = '';
        if (repFields) repFields.style.display = 'flex';
    }
}

function updateDepotStockNotice() {
    var select = document.getElementById('modal_center_id');
    var opt = select.options[select.selectedIndex];
    var notice = document.getElementById('depotStockNotice');

    if (opt && opt.value) {
        var stock = parseFloat(opt.getAttribute('data-stock') || '0');
        if (stock <= 0) {
            notice.innerHTML = '<span class="text-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Warning: Zero stock reported in this depot!</span>';
        } else {
            notice.innerHTML = '<span class="text-success font-weight-bold"><i class="fa-solid fa-circle-check mr-1"></i> Available physical stock: ' + stock + ' units.</span>';
        }
    } else {
        notice.innerHTML = 'Select the depot where physical stock is being handed over.';
    }
}

function openEditDisbursementModal(data) {
    document.getElementById('editDisbursementForm').action = data.update_url;
    document.getElementById('editDisbNoLabel').innerText = '#' + data.disbursement_no;
    document.getElementById('edit_disb_app_no').innerText = '#' + data.application_no;
    document.getElementById('edit_disb_center_id').value = data.center_id;
    document.getElementById('edit_disb_item_id').value = data.item_id;
    document.getElementById('edit_disb_qty').value = data.quantity;
    document.getElementById('edit_disb_date').value = data.date;
    document.getElementById('edit_disb_recip_name').value = data.recipient_name || '';
    document.getElementById('edit_disb_recip_cnic').value = data.recipient_cnic || '';
    document.getElementById('edit_disb_recip_phone').value = data.recipient_phone || '';
    document.getElementById('edit_disb_representative').value = data.representative || '';
    document.getElementById('edit_disb_challan').value = data.challan || '';
    document.getElementById('edit_disb_notes').value = data.notes || '';
    document.getElementById('edit_disb_justification').value = '';

    var windowText = document.getElementById('editDisbWindowText');
    var alertBox = document.getElementById('editDisbGraceAlert');
    if (data.is_super_admin) {
        alertBox.className = 'alert alert-primary py-2 small mb-3';
        windowText.innerHTML = '<i class="fa-solid fa-shield-halved mr-1"></i> <strong>Super Admin Access:</strong> You can edit this disbursement anytime. All changes require justification and are permanently audited.';
    } else {
        alertBox.className = 'alert alert-warning py-2 small mb-3';
        windowText.innerHTML = '<i class="fa-solid fa-stopwatch mr-1"></i> <strong>Initial Edit Window Active:</strong> ' + data.mins_left + ' minutes remaining to adjust values. After 10 minutes, editing locks to Admin only.';
    }

    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#editDisbursementModal').modal('show');
    } else {
        var m = document.getElementById('editDisbursementModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }
}

function closeEditDisbursementModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#editDisbursementModal').modal('hide');
    } else {
        var m = document.getElementById('editDisbursementModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }
}

function showDisbursementAuditModal(title, logs) {
    document.getElementById('disbursementAuditModalSubject').innerText = title;
    var container = document.getElementById('disbursementAuditModalBody');
    container.innerHTML = '';

    if (!logs || logs.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-3">No change logs recorded yet.</p>';
    } else {
        logs.forEach(function (log) {
            var card = document.createElement('div');
            card.className = 'card border mb-3 shadow-sm';

            var changesHtml = '';
            if (log.changed_fields && Object.keys(log.changed_fields).length > 0) {
                changesHtml += '<table class="table table-sm table-bordered mt-2 mb-0" style="font-size: 11px;">';
                changesHtml += '<thead class="thead-light"><tr><th>Field</th><th>Original Value</th><th>Modified Value</th></tr></thead><tbody>';
                for (var f in log.changed_fields) {
                    var change = log.changed_fields[f];
                    var fromVal = (change.from !== null && change.from !== undefined) ? change.from : '<em>empty</em>';
                    var toVal = (change.to !== null && change.to !== undefined) ? change.to : '<em>empty</em>';
                    changesHtml += '<tr><td class="font-weight-bold">' + f + '</td><td class="text-danger">' + fromVal + '</td><td class="text-success">' + toVal + '</td></tr>';
                }
                changesHtml += '</tbody></table>';
            }

            card.innerHTML =
                '<div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + log.user + '</strong> <span class="badge badge-secondary ml-1">' + log.role + '</span></div>' +
                    '<small class="text-muted"><i class="fa-solid fa-clock mr-1"></i>' + log.date + '</small>' +
                '</div>' +
                '<div class="card-body p-3">' +
                    '<div class="mb-2"><strong class="text-dark">Reason / Justification:</strong> <span class="text-primary font-weight-bold">' + (log.justification || 'None') + '</span></div>' +
                    changesHtml +
                '</div>';
            container.appendChild(card);
        });
    }

    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#disbursementAuditModal').modal('show');
    } else {
        var m = document.getElementById('disbursementAuditModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }
}

function closeDisbursementAuditModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#disbursementAuditModal').modal('hide');
    } else {
        var m = document.getElementById('disbursementAuditModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
    }
}

@if($preselectedApplication)
document.addEventListener('DOMContentLoaded', function () {
    @php
        $preQuota = $preselectedApplication->getRemainingDisbursementQuantity();
        $preStockItem = $preselectedApplication->scheme?->stockItems?->first();
    @endphp
    openDisburseModal({
        id: {{ $preselectedApplication->id }},
        app_no: '{{ $preselectedApplication->application_no }}',
        name: '{{ addslashes($preselectedApplication->applicant->name ?? '') }}',
        cnic: '{{ $preselectedApplication->applicant->cnic ?? '' }}',
        phone: '{{ $preselectedApplication->applicant->phone ?? '' }}',
        scheme_id: {{ $preselectedApplication->scheme_id }},
        scheme_title: '{{ addslashes($preselectedApplication->scheme->title ?? '') }}',
        item_id: {{ $preStockItem->id ?? 0 }},
        item_name: '{{ addslashes($preStockItem->item_name ?? '') }}',
        unit: '{{ addslashes($preStockItem->unit ?? 'Bags') }}',
        demanded_qty: {{ $preselectedApplication->getDemandedQuantity() }},
        approved_qty: {{ $preselectedApplication->getApprovedQuantity() ?? $preQuota }},
        remaining: {{ $preQuota }},
        challan_no: '{{ $preselectedApplication->challan_no ?? '' }}',
        district_id: {{ $preselectedApplication->district_id }}
    });
});
@endif
</script>
@endpush

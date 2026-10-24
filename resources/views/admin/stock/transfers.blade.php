@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-info text-white">
                <i class="fa-solid fa-arrow-right-arrow-left"></i>
            </div>
            <div>
                Inter-Center Stock Transfers
                <div class="page-title-subheading">
                    Transfer stock between collection centers and depots based on verified beneficiary demand with mandatory justification.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <button type="button" class="btn btn-info shadow-sm" data-toggle="modal" data-target="#createTransferModal">
                <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> New Stock Transfer
            </button>
        </div>
    </div>
</div>

{{-- Transfers Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-route mr-2 text-info"></i> Stock Movement & Transfer History ({{ $transfers->total() }})</span>
        <span class="badge badge-light border text-muted">All transfers update center balances in real time</span>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Transfer #</th>
                    <th>Scheme Item</th>
                    <th>Source Center (From)</th>
                    <th>Destination Center (To)</th>
                    <th class="text-right">Quantity</th>
                    <th style="min-width: 250px;">Demand Justification</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Transferred By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                <tr>
                    <td>
                        <span class="font-weight-bold text-info">#{{ $t->transfer_no }}</span>
                    </td>
                    <td>
                        <strong>{{ $t->item->item_name ?? 'Scheme Item' }}</strong>
                        <div class="small text-muted">{{ $t->item->scheme->title ?? '' }}</div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $t->fromCenter->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $t->fromCenter->district->name ?? '' }} ({{ $t->fromCenter->level }})</small>
                    </td>
                    <td>
                        <div class="font-weight-bold text-success">{{ $t->toCenter->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $t->toCenter->district->name ?? '' }} ({{ $t->toCenter->level }})</small>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-primary font-size-md px-2 py-1">
                            {{ number_format($t->quantity) }} {{ $t->item->unit ?? 'Units' }}
                        </span>
                    </td>
                    <td>
                        <div class="p-2 rounded bg-light border-left border-info small">
                            <i class="fa-solid fa-quote-left text-info mr-1"></i>
                            <strong>Justification:</strong> {{ $t->demand_justification }}
                        </div>
                        @if($t->notes)
                            <div class="small text-muted mt-1"><i class="fa-solid fa-note-sticky mr-1"></i> {{ $t->notes }}</div>
                        @endif
                    </td>
                    <td>
                        <i class="fa-solid fa-calendar-day text-muted mr-1"></i> {{ $t->transfer_date->format('d M Y') }}
                    </td>
                    <td>
                        @if($t->status === 'completed')
                            <span class="badge badge-success"><i class="fa-solid fa-check-circle mr-1"></i> Completed</span>
                        @elseif($t->status === 'pending')
                            <span class="badge badge-warning"><i class="fa-solid fa-clock mr-1"></i> In Transit</span>
                        @else
                            <span class="badge badge-danger">Cancelled</span>
                        @endif
                    </td>
                    <td>
                        <small class="d-block">{{ $t->transferredBy->name ?? 'Officer' }}</small>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No stock transfers recorded yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $transfers->firstItem() ?? 0 }} to {{ $transfers->lastItem() ?? 0 }} of {{ $transfers->total() }}</span>
        {{ $transfers->links() }}
    </div>
</div>

{{-- New Transfer Modal --}}
<div class="modal fade" id="createTransferModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.stock.transfers.store') }}">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> Execute Demand-Based Stock Transfer</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small">
                        <i class="fa-solid fa-circle-info mr-1"></i> <strong>Audit Compliance:</strong> Stock transfers between centers require explicit justification referencing beneficiary demand surges or deficit in receiving center.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Source Center (Transfer From) *</label>
                            <select name="from_center_id" id="from_center_id" class="form-control" required>
                                <option value="">Select Origin Depot / Center</option>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}" {{ request('from_center_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} (Available: {{ number_format($c->inventories->sum('current_balance')) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Destination Center (Transfer To) *</label>
                            <select name="to_center_id" id="to_center_id" class="form-control" required>
                                <option value="">Select Receiving Depot / Center</option>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}" {{ request('to_center_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ $c->level }} - {{ $c->district->name ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" class="form-control" required>
                                <option value="">Select Scheme Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_name }} ({{ $item->unit }}) - {{ $item->scheme->title ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">Quantity *</label>
                            <input type="number" step="any" min="0.01" name="quantity" class="form-control font-weight-bold text-primary" placeholder="e.g. 250" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">Transfer Date *</label>
                            <input type="date" name="transfer_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Demand Justification (Mandatory) *</label>
                        <textarea name="demand_justification" rows="3" class="form-control" placeholder="Specify why this transfer is required, e.g.: High concentration of 80 approved wheat seed beneficiaries in Tehsil Danyore awaiting immediate seed disbursement before sowing season deadline..." required minlength="10"></textarea>
                        <small class="text-muted">Must explain the approved beneficiary demand or deficit necessity.</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Additional Dispatch Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Vehicle number, driver name, expected arrival date..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info font-weight-bold"><i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Authorize & Execute Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

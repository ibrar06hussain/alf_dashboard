@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-info text-white shadow-sm">
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
            <button type="button" class="btn btn-info btn-lg shadow font-weight-bold px-3" onclick="openTransferModal()" data-toggle="modal" data-target="#createTransferModal">
                <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> New Stock Transfer
            </button>
        </div>
    </div>
</div>

{{-- Transfers Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-route mr-2 text-info"></i> Stock Movement & Transfer History ({{ $transfers->total() }})
        </span>
        <button type="button" class="btn btn-info btn-sm shadow-sm font-weight-bold px-3" onclick="openTransferModal()" data-toggle="modal" data-target="#createTransferModal">
            <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> New Stock Transfer
        </button>
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
                    <td colspan="9" class="text-center py-5">
                        <div class="py-4">
                            <div class="avatar-icon-wrapper rounded-circle bg-light text-info p-3 mb-3 mx-auto" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-arrow-right-arrow-left fa-2x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-1">No Stock Transfers Recorded Yet</h5>
                            <p class="text-muted small mb-3" style="max-width: 460px; margin: 0 auto;">
                                Transfer available scheme items from central supply depots to tehsil centers or village points with required demand justification.
                            </p>
                            <button type="button" class="btn btn-info btn-lg shadow font-weight-bold px-4" onclick="openTransferModal()" data-toggle="modal" data-target="#createTransferModal">
                                <i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> New Stock Transfer Now
                            </button>
                        </div>
                    </td>
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
@endsection

@push('modals')
{{-- New Transfer Modal --}}
<div class="modal fade" id="createTransferModal" tabindex="-1" role="dialog" aria-labelledby="createTransferModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="{{ route('admin.stock.transfers.store') }}">
                @csrf
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title font-weight-bold" id="createTransferModalTitle">
                        <i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> Execute Demand-Based Stock Transfer
                    </h5>
                    <button type="button" class="close text-white" onclick="closeTransferModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 small">
                        <i class="fa-solid fa-circle-info mr-1"></i> <strong>Audit Compliance:</strong> Stock transfers between centers require explicit justification referencing beneficiary demand surges or deficit in receiving center.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Source Center (Transfer From) *</label>
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
                            <label class="font-weight-bold text-dark">Destination Center (Transfer To) *</label>
                            <select name="to_center_id" id="to_center_id" class="form-control" required>
                                <option value="">Select Receiving Depot / Center</option>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}" {{ request('to_center_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ ucfirst($c->level) }} - {{ $c->district->name ?? '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" class="form-control" required>
                                <option value="">Select Scheme Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_name }} ({{ $item->unit }}) - {{ $item->scheme->title ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-dark">Quantity *</label>
                            <input type="number" step="any" min="0.01" name="quantity" class="form-control font-weight-bold text-primary" placeholder="e.g. 250" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold text-dark">Transfer Date *</label>
                            <input type="date" name="transfer_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Demand Justification (Mandatory) *</label>
                        <textarea name="demand_justification" rows="3" class="form-control" placeholder="Specify why this transfer is required, e.g.: High concentration of 80 approved wheat seed beneficiaries in Tehsil Danyore awaiting immediate seed disbursement before sowing season deadline..." required minlength="10"></textarea>
                        <small class="text-muted">Must explain the approved beneficiary demand or deficit necessity.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Additional Dispatch Notes</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Vehicle number, driver name, expected arrival date..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeTransferModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i> Authorize & Execute Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openTransferModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createTransferModal').modal('show');
    } else {
        var m = document.getElementById('createTransferModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.getElementById('transferModalBackdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'transferModalBackdrop';
                backdrop.onclick = closeTransferModal;
                document.body.appendChild(backdrop);
            }
        }
    }
}

function closeTransferModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createTransferModal').modal('hide');
    } else {
        var m = document.getElementById('createTransferModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
            var backdrop = document.getElementById('transferModalBackdrop');
            if (backdrop) backdrop.remove();
        }
    }
}
</script>
@endpush

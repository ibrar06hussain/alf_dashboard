@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-success text-white shadow-sm">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <div>
                Scheme Stock Receipts (Stock In)
                <div class="page-title-subheading">
                    Record and manage consignments received from suppliers, corporations, and central seed depots into collection centers.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <button type="button" class="btn btn-success btn-lg shadow font-weight-bold px-3" onclick="openReceiptModal()" data-toggle="modal" data-target="#createReceiptModal">
                <i class="fa-solid fa-plus-circle mr-1"></i> Record Stock In
            </button>
        </div>
    </div>
</div>

{{-- Filters Card --}}
<div class="main-card mb-3 card shadow-sm">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.stock.receipts') }}" class="form-row align-items-center">
            <div class="col-md-5 my-1">
                <select name="center_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All Receiving Collection Centers</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" {{ request('center_id') == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 my-1">
                <select name="item_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All Scheme Stock Items</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>{{ $item->item_name }} ({{ $item->scheme->title ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 my-1 text-right">
                <a href="{{ route('admin.stock.receipts') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left mr-1"></i> Reset Filter</a>
            </div>
        </form>
    </div>
</div>

{{-- Receipts Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-file-invoice mr-2 text-success"></i> Stock Consignment Ledger ({{ $receipts->total() }})
        </span>
        <button type="button" class="btn btn-success btn-sm shadow-sm font-weight-bold px-3" onclick="openReceiptModal()" data-toggle="modal" data-target="#createReceiptModal">
            <i class="fa-solid fa-plus-circle mr-1"></i> Record Stock In
        </button>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Receipt No</th>
                    <th>Scheme Item</th>
                    <th>Receiving Depot / Center</th>
                    <th class="text-right">Quantity Received</th>
                    <th>Date Received</th>
                    <th>Supplier / Source</th>
                    <th>Batch / Lot No</th>
                    <th>Receiver</th>
                    <th class="text-center">Proof / Voucher</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                <tr>
                    <td>
                        <span class="font-weight-bold text-primary">#{{ $r->receipt_no }}</span>
                    </td>
                    <td>
                        <strong>{{ $r->item->item_name ?? 'Scheme Item' }}</strong>
                        <div class="small text-muted">{{ $r->item->scheme->title ?? '' }}</div>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $r->center->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $r->center->district->name ?? '' }} ({{ $r->center->level }})</small>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-success font-size-md px-2 py-1">
                            +{{ number_format($r->quantity_received) }} {{ $r->item->unit ?? 'Units' }}
                        </span>
                    </td>
                    <td>
                        <i class="fa-solid fa-calendar-day text-muted mr-1"></i> {{ $r->received_date->format('d M Y') }}
                    </td>
                    <td>
                        <span class="text-dark">{{ $r->supplier_source ?? 'Government Supply Depot' }}</span>
                    </td>
                    <td>
                        @if($r->batch_no)
                            <code>{{ $r->batch_no }}</code>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <small class="d-block">{{ $r->receiver->name ?? 'Staff' }}</small>
                    </td>
                    <td class="text-center">
                        @if($r->proof_attachment_path)
                            <a href="{{ asset('storage/' . $r->proof_attachment_path) }}" target="_blank" class="btn btn-xs btn-outline-info" title="View Proof Voucher">
                                <i class="fa-solid fa-paperclip mr-1"></i> View Proof
                            </a>
                        @else
                            <span class="text-muted small">None</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="py-4">
                            <div class="avatar-icon-wrapper rounded-circle bg-light text-success p-3 mb-3 mx-auto" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-truck-ramp-box fa-2x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-1">No Stock Receipts Recorded Yet</h5>
                            <p class="text-muted small mb-3" style="max-width: 480px; margin: 0 auto;">
                                Register incoming consignments of wheat seed, fertilizers, fingerlings, or scheme items into your district or tehsil collection centers to track physical inventory.
                            </p>
                            <button type="button" class="btn btn-success btn-lg shadow font-weight-bold px-4" onclick="openReceiptModal()" data-toggle="modal" data-target="#createReceiptModal">
                                <i class="fa-solid fa-plus-circle mr-2"></i> Record Stock In Now
                            </button>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $receipts->firstItem() ?? 0 }} to {{ $receipts->lastItem() ?? 0 }} of {{ $receipts->total() }}</span>
        {{ $receipts->links() }}
    </div>
</div>
@endsection

@push('modals')
{{-- Create Receipt Modal --}}
<div class="modal fade" id="createReceiptModal" tabindex="-1" role="dialog" aria-labelledby="createReceiptModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="{{ route('admin.stock.receipts.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="createReceiptModalTitle">
                        <i class="fa-solid fa-truck-ramp-box mr-2"></i> Record Stock In (Items Received)
                    </h5>
                    <button type="button" class="close text-white" onclick="closeReceiptModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border-left border-success py-2 mb-3 small text-dark">
                        <i class="fa-solid fa-circle-check text-success mr-1"></i>
                        Recording stock in increments the available inventory balance in the selected collection depot in real time.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Destination Collection Center *</label>
                            <select name="collection_center_id" class="form-control" required>
                                <option value="">Select Receiving Depot / Center</option>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}" {{ request('center_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ ucfirst($c->level) }} - {{ $c->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" class="form-control" required>
                                <option value="">Select Scheme Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" {{ request('item_id') == $item->id ? 'selected' : '' }}>
                                        {{ $item->item_name }} ({{ $item->unit }}) - {{ $item->scheme->title ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Quantity Received *</label>
                            <input type="number" step="any" min="0.01" name="quantity_received" class="form-control form-control-lg font-weight-bold text-success" placeholder="e.g. 500" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Received Date *</label>
                            <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Supplier / Source Organization</label>
                            <input type="text" name="supplier_source" class="form-control" placeholder="e.g. Punjab Seed Corporation / FSC&RD">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Batch / Lot Number</label>
                            <input type="text" name="batch_no" class="form-control" placeholder="e.g. LOT-WHT-2026-9B">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Proof Attachment (Delivery Note / Gate Pass / Challan / Photo)</label>
                        <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">Upload scanned delivery receipt or physical consignment photo (Max: 10MB)</small>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Notes / Verification Remarks</label>
                        <textarea name="notes" rows="2" class="form-control" placeholder="Condition of bags on arrival, delivery truck number, driver contact..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeReceiptModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-check mr-1"></i> Confirm & Add to Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openReceiptModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createReceiptModal').modal('show');
    } else {
        var m = document.getElementById('createReceiptModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.getElementById('receiptModalBackdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'receiptModalBackdrop';
                backdrop.onclick = closeReceiptModal;
                document.body.appendChild(backdrop);
            }
        }
    }
}

function closeReceiptModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createReceiptModal').modal('hide');
    } else {
        var m = document.getElementById('createReceiptModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
            var backdrop = document.getElementById('receiptModalBackdrop');
            if (backdrop) backdrop.remove();
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('action') === 'record' || params.get('record') === '1' || window.location.hash === '#record' || window.location.hash === '#createReceiptModal') {
        setTimeout(openReceiptModal, 200);
    }
});
</script>
@endpush

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
                    <th class="text-center">Action / Audit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                @php
                    $currentUser = auth()->user();
                    $canEditReceipt = $r->canUserEdit($currentUser);
                    $hasUnrestrictedEdit = $currentUser?->hasAnyRole(['Super Admin', 'Distribution Officer']) ?? false;
                    $minsLeft = $r->editMinutesRemaining();
                    $hasAudit = $r->editLogs->isNotEmpty();
                @endphp
                <tr>
                    <td>
                        <span class="fw-bold font-weight-bold text-primary">#{{ $r->receipt_no }}</span>
                        @if($hasAudit)
                            <div class="mt-1">
                                <span class="badge bg-warning text-dark fw-semibold" style="font-size: 11px;" title="This entry was edited {{ $r->editLogs->count() }} time(s)">
                                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Edited ({{ $r->editLogs->count() }})
                                </span>
                            </div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $r->item->item_name ?? 'Scheme Item' }}</strong>
                        <div class="small text-muted">{{ $r->item->scheme->title ?? '' }}</div>
                    </td>
                    <td>
                        <div class="fw-bold font-weight-bold text-dark">{{ $r->center->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $r->center->district->name ?? '' }} ({{ $r->center->level }})</small>
                    </td>
                    <td class="text-end text-right">
                        <span class="badge fw-bold px-3 py-2" style="background-color: #dcfce7; color: #064e3b; border: 1.5px solid #15803d; font-size: 0.92rem; letter-spacing: 0.01em;">
                            +{{ number_format($r->quantity_received) }} {{ $r->item->unit ?? 'Units' }}
                        </span>
                    </td>
                    <td>
                        <i class="fa-solid fa-calendar-day text-muted me-1"></i> {{ $r->received_date->format('d M Y') }}
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
                        <small class="d-block text-dark">{{ $r->receiver->name ?? 'Staff' }}</small>
                    </td>
                    <td class="text-center">
                        @if($r->proof_attachment_path)
                            <a href="{{ asset('storage/' . $r->proof_attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-info" title="View Proof Voucher">
                                <i class="fa-solid fa-paperclip me-1"></i> View Proof
                            </a>
                        @else
                            <span class="text-muted small">None</span>
                        @endif
                    </td>
                    <td class="text-center text-nowrap">
                        @if($canEditReceipt)
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold font-weight-bold shadow-sm"
                                    onclick="openEditReceiptModal({{ json_encode([
                                        'id' => $r->id,
                                        'receipt_no' => $r->receipt_no,
                                        'center_id' => $r->collection_center_id,
                                        'item_id' => $r->scheme_stock_item_id,
                                        'quantity' => (float) $r->quantity_received,
                                        'date' => $r->received_date->format('Y-m-d'),
                                        'supplier' => $r->supplier_source,
                                        'batch' => $r->batch_no,
                                        'notes' => $r->notes,
                                        'update_url' => route('admin.stock.receipts.update', $r),
                                        'mins_left' => $minsLeft,
                                        'has_unrestricted_edit' => $hasUnrestrictedEdit,
                                        'is_super_admin' => $currentUser?->hasRole('Super Admin') ?? false,
                                        'is_distribution_officer' => $currentUser?->hasRole('Distribution Officer') ?? false,
                                    ]) }})"
                                    title="{{ $hasUnrestrictedEdit ? 'Edit Stock In Entry' : 'User Edit ('.$minsLeft.' mins left in 10-min grace window)' }}">
                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                @if(!$hasUnrestrictedEdit)
                                    <span class="badge bg-light text-dark border ms-1">{{ $minsLeft }}m</span>
                                @endif
                            </button>
                        @else
                            <span class="badge bg-light text-secondary border" title="Edit window expired (>10 mins). Only Super Admin or authorized Distribution Officer can modify.">
                                <i class="fa-solid fa-lock me-1"></i> Locked
                            </span>
                        @endif

                        @if($hasAudit)
                            <button type="button" class="btn btn-sm btn-info text-white fw-bold ms-1"
                                    onclick="showAuditLogModal('Receipt #{{ $r->receipt_no }}', {{ json_encode($r->editLogs->map(function($l) {
                                        return [
                                            'date' => $l->created_at->format('d M Y H:i:s'),
                                            'user' => $l->user->name ?? 'System',
                                            'role' => $l->user_role ?? 'Officer',
                                            'justification' => $l->justification,
                                            'changed_fields' => $l->changed_fields,
                                        ];
                                    })) }})"
                                    title="View Full Edit History & Justifications">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> History ({{ $r->editLogs->count() }})
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-5">
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

{{-- Permanent Edit History & Audit Log Table --}}
<div class="main-card mb-4 card shadow-sm" id="receiptEditHistoryCard">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="fw-bold font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-clock-rotate-left me-2 text-warning"></i> Stock Receipt Edit History Log ({{ isset($recentEditLogs) ? $recentEditLogs->count() : 0 }})
        </span>
        <span class="badge bg-light text-dark border">Immutable Audit Trail</span>
    </div>
    @if(isset($recentEditLogs) && $recentEditLogs->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light thead-light">
                    <tr>
                        <th>Edited At</th>
                        <th>Receipt No</th>
                        <th>Edited By</th>
                        <th>Justification</th>
                        <th>Field Changes (Old &rarr; New)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentEditLogs as $log)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td class="fw-bold text-primary">#{{ $log->reference_no }}</td>
                            <td>
                                <strong class="text-dark">{{ $log->user?->name ?? 'System' }}</strong>
                                <span class="badge bg-secondary text-white ms-1">{{ $log->user_role ?? 'Officer' }}</span>
                            </td>
                            <td class="text-dark">{{ $log->justification }}</td>
                            <td>
                                @if(!empty($log->changed_fields))
                                    <div class="d-flex flex-column gap-1">
                                        @foreach($log->changed_fields as $fieldKey => $diff)
                                            @continue(in_array($fieldKey, ['collection_center_id', 'scheme_stock_item_id'], true) && (isset($log->changed_fields['collection_center_name']) || isset($log->changed_fields['scheme_stock_item_name'])))
                                            <div>
                                                <span class="fw-semibold text-dark">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $fieldKey)) }}:</span>
                                                <span class="text-danger text-decoration-line-through">{{ $diff['from'] ?? '—' }}</span>
                                                <i class="fa-solid fa-arrow-right mx-1 text-muted"></i>
                                                <span class="fw-bold" style="color: #064e3b;">{{ $diff['to'] ?? '—' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">Proof attachment or minor details updated</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card-body text-center text-muted py-4 small">
            <i class="fa-solid fa-shield-check text-success me-1"></i> No stock receipt edits have been recorded yet. All future edits will be logged permanently here with old values, new values, and justification.
        </div>
    @endif
</div>
@endsection

@push('modals')
{{-- Edit Receipt Modal --}}
<div class="modal fade" id="editReceiptModal" tabindex="-1" role="dialog" aria-labelledby="editReceiptModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form id="editReceiptForm" method="POST" action="" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title font-weight-bold" id="editReceiptModalTitle">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Edit Stock In (<span id="editReceiptNoLabel"></span>)
                    </h5>
                    <button type="button" class="close" onclick="closeEditReceiptModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div id="editReceiptGraceAlert" class="alert alert-info py-2 small mb-3">
                        <i class="fa-solid fa-clock mr-1"></i> <strong id="editReceiptWindowText">10-Minute Edit Window Active</strong>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Collection Center / Depot *</label>
                            <select name="collection_center_id" id="edit_receipt_center_id" class="form-control" required>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} ({{ $c->district->name ?? '' }} - {{ ucfirst($c->level) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" id="edit_receipt_item_id" class="form-control" required>
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
                            <label class="font-weight-bold text-dark">Quantity Received *</label>
                            <input type="number" step="0.01" min="0.01" name="quantity_received" id="edit_receipt_qty" class="form-control" required>
                            <small class="text-muted">Warehouse balance will automatically adjust for differences.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Received Date *</label>
                            <input type="date" name="received_date" id="edit_receipt_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Supplier / Source Organization</label>
                            <input type="text" name="supplier_source" id="edit_receipt_supplier" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Batch / Lot Number</label>
                            <input type="text" name="batch_no" id="edit_receipt_batch" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Replace Proof Attachment (Optional)</label>
                        <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf">
                        <small class="text-muted">Leave empty to keep existing proof document.</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Notes / Remarks</label>
                        <textarea name="notes" id="edit_receipt_notes" rows="2" class="form-control"></textarea>
                    </div>

                    {{-- MANDATORY JUSTIFICATION --}}
                    <div class="form-group p-3 border border-warning rounded bg-light mb-0">
                        <label class="font-weight-bold text-danger">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Mandatory Justification for Change *
                        </label>
                        <textarea name="justification" id="edit_receipt_justification" rows="2" class="form-control" required minlength="5" placeholder="Specify why this entry is being edited (e.g., corrected typo in supplier quantity, adjusted batch number per delivery invoice)..."></textarea>
                        <small class="text-muted">Every edit, old and new values, and this justification are permanently preserved in the audit log.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeEditReceiptModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-save mr-1"></i> Save Changes & Recalculate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Generic Audit Trail Modal --}}
<div class="modal fade" id="stockAuditModal" tabindex="-1" role="dialog" aria-labelledby="stockAuditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="stockAuditModalTitle">
                    <i class="fa-solid fa-history mr-2"></i> Audit Trail & Change History
                </h5>
                <button type="button" class="close text-white" onclick="closeAuditModal()" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <h6 class="font-weight-bold text-primary mb-3" id="auditModalSubject"></h6>
                <div id="auditModalBody" style="max-height: 420px; overflow-y: auto;">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" onclick="closeAuditModal()" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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
                            <input type="number" step="any" min="0.01" name="quantity_received" class="form-control form-control-lg fw-bold font-weight-bold text-dark" style="color: #064e3b;" placeholder="e.g. 500" required>
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

function showBsOrFallbackModal(modalId) {
    var m = document.getElementById(modalId);
    if (!m) return;
    if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(m).show();
    } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#' + modalId).modal('show');
    } else {
        m.classList.add('show');
        m.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

function hideBsOrFallbackModal(modalId) {
    var m = document.getElementById(modalId);
    if (!m) return;
    if (window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(m).hide();
    } else if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#' + modalId).modal('hide');
    } else {
        m.classList.remove('show');
        m.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

function escapeReceiptHtml(val) {
    if (val === null || val === undefined) return '';
    return String(val)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openEditReceiptModal(data) {
    document.getElementById('editReceiptForm').action = data.update_url;
    document.getElementById('editReceiptNoLabel').innerText = '#' + data.receipt_no;
    document.getElementById('edit_receipt_center_id').value = data.center_id;
    document.getElementById('edit_receipt_item_id').value = data.item_id;
    document.getElementById('edit_receipt_qty').value = data.quantity;
    document.getElementById('edit_receipt_date').value = data.date;
    document.getElementById('edit_receipt_supplier').value = data.supplier || '';
    document.getElementById('edit_receipt_batch').value = data.batch || '';
    document.getElementById('edit_receipt_notes').value = data.notes || '';
    document.getElementById('edit_receipt_justification').value = '';

    var windowText = document.getElementById('editReceiptWindowText');
    var alertBox = document.getElementById('editReceiptGraceAlert');
    if (data.is_super_admin) {
        alertBox.className = 'alert alert-primary py-2 small mb-3';
        windowText.innerHTML = '<i class="fa-solid fa-shield-halved me-1"></i> <strong>Super Admin Access:</strong> You can edit this entry anytime. All changes require justification and are logged permanently.';
    } else if (data.is_distribution_officer || data.has_unrestricted_edit) {
        alertBox.className = 'alert alert-primary py-2 small mb-3';
        windowText.innerHTML = '<i class="fa-solid fa-user-check me-1"></i> <strong>Distribution Officer Access:</strong> You can edit this entry anytime within your assigned district. Every change requires justification and is saved in the permanent edit history.';
    } else {
        alertBox.className = 'alert alert-warning py-2 small mb-3';
        windowText.innerHTML = '<i class="fa-solid fa-stopwatch me-1"></i> <strong>Initial Edit Window Active:</strong> ' + data.mins_left + ' minutes remaining to adjust values. After 10 minutes, only Super Admin or Distribution Officer can modify.';
    }

    showBsOrFallbackModal('editReceiptModal');
}

function closeEditReceiptModal() {
    hideBsOrFallbackModal('editReceiptModal');
}

function showAuditLogModal(title, logs) {
    document.getElementById('auditModalSubject').innerText = title;
    var container = document.getElementById('auditModalBody');
    container.innerHTML = '';

    if (!logs || logs.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-3">No change logs recorded yet.</p>';
    } else {
        logs.forEach(function (log) {
            var card = document.createElement('div');
            card.className = 'card border mb-3 shadow-sm';

            var changesHtml = '';
            if (log.changed_fields && Object.keys(log.changed_fields).length > 0) {
                changesHtml += '<table class="table table-sm table-bordered mt-2 mb-0" style="font-size: 12px;">';
                changesHtml += '<thead class="table-light thead-light"><tr><th>Field</th><th>Original Value</th><th>Modified Value</th></tr></thead><tbody>';
                for (var f in log.changed_fields) {
                    var change = log.changed_fields[f];
                    var fromVal = (change.from !== null && change.from !== undefined && change.from !== '') ? escapeReceiptHtml(change.from) : '<em>empty</em>';
                    var toVal = (change.to !== null && change.to !== undefined && change.to !== '') ? escapeReceiptHtml(change.to) : '<em>empty</em>';
                    var fieldLabel = escapeReceiptHtml(f.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }));
                    changesHtml += '<tr><td class="fw-bold font-weight-bold">' + fieldLabel + '</td><td class="text-danger">' + fromVal + '</td><td class="fw-bold font-weight-bold" style="color: #064e3b;">' + toVal + '</td></tr>';
                }
                changesHtml += '</tbody></table>';
            }

            card.innerHTML =
                '<div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">' +
                    '<div><strong>' + escapeReceiptHtml(log.user) + '</strong> <span class="badge bg-secondary text-white ms-1">' + escapeReceiptHtml(log.role) + '</span></div>' +
                    '<small class="text-muted"><i class="fa-solid fa-clock me-1"></i>' + escapeReceiptHtml(log.date) + '</small>' +
                '</div>' +
                '<div class="card-body p-3">' +
                    '<div class="mb-2"><strong class="text-dark">Reason / Justification:</strong> <span class="text-primary fw-bold font-weight-bold">' + escapeReceiptHtml(log.justification || 'None') + '</span></div>' +
                    changesHtml +
                '</div>';
            container.appendChild(card);
        });
    }

    showBsOrFallbackModal('stockAuditModal');
}

function closeAuditModal() {
    hideBsOrFallbackModal('stockAuditModal');
}
</script>
@endpush

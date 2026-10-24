@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-danger text-white shadow-sm">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                Wasted & Damaged Stock Records
                <div class="page-title-subheading">
                    Document spoiled, damaged, or ruptured items with mandatory photographic or inspection report evidence.
                </div>
            </div>
        </div>
        <div class="page-title-actions">
            <button type="button" class="btn btn-danger btn-lg shadow font-weight-bold px-3" onclick="openDamageModal()" data-toggle="modal" data-target="#createDamageModal">
                <i class="fa-solid fa-plus-circle mr-1"></i> Record Damaged Stock
            </button>
        </div>
    </div>
</div>

{{-- Damages Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <span class="font-weight-bold text-dark font-size-md">
            <i class="fa-solid fa-dumpster-fire mr-2 text-danger"></i> Damaged & Wasted Stock Audit Log ({{ $damages->total() }})
        </span>
        <button type="button" class="btn btn-danger btn-sm shadow-sm font-weight-bold px-3" onclick="openDamageModal()" data-toggle="modal" data-target="#createDamageModal">
            <i class="fa-solid fa-plus-circle mr-1"></i> Record Damaged Stock
        </button>
    </div>
    <div class="table-responsive">
        <table class="align-middle mb-0 table table-borderless table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Ref #</th>
                    <th>Depot / Center</th>
                    <th>Scheme Item</th>
                    <th class="text-right">Quantity Damaged</th>
                    <th>Damage Type</th>
                    <th style="min-width: 200px;">Reason & Incident Details</th>
                    <th>Incident Date</th>
                    <th>Recorded By</th>
                    <th class="text-center">Evidence Proof</th>
                </tr>
            </thead>
            <tbody>
                @forelse($damages as $d)
                <tr>
                    <td>
                        <span class="font-weight-bold text-danger">#{{ $d->reference_no }}</span>
                    </td>
                    <td>
                        <div class="font-weight-bold text-dark">{{ $d->center->name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $d->center->district->name ?? '' }} ({{ $d->center->level }})</small>
                    </td>
                    <td>
                        <strong>{{ $d->item->item_name ?? 'Scheme Item' }}</strong>
                        <div class="small text-muted">{{ $d->item->scheme->title ?? '' }}</div>
                    </td>
                    <td class="text-right">
                        <span class="badge badge-danger font-size-md px-2 py-1">
                            -{{ number_format($d->quantity) }} {{ $d->item->unit ?? 'Units' }}
                        </span>
                    </td>
                    <td>
                        @php
                            $typeLabels = [
                                'spoilage' => ['label' => 'Spoilage', 'class' => 'badge-danger'],
                                'packaging_tear' => ['label' => 'Packaging Tear', 'class' => 'badge-warning'],
                                'water_moisture' => ['label' => 'Water/Moisture', 'class' => 'badge-info'],
                                'pest_infestation' => ['label' => 'Pest Infestation', 'class' => 'badge-danger'],
                                'transit_breakage' => ['label' => 'Transit Breakage', 'class' => 'badge-secondary'],
                                'expiry' => ['label' => 'Expired', 'class' => 'badge-dark'],
                                'other' => ['label' => 'Other Loss', 'class' => 'badge-secondary'],
                            ];
                            $meta = $typeLabels[$d->damage_type] ?? ['label' => ucfirst($d->damage_type), 'class' => 'badge-secondary'];
                        @endphp
                        <span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                    </td>
                    <td>
                        <div class="small text-dark">{{ $d->reason }}</div>
                        @if($d->action_taken)
                            <div class="small text-muted mt-1"><i class="fa-solid fa-shield mr-1 text-success"></i> <strong>Action:</strong> {{ $d->action_taken }}</div>
                        @endif
                    </td>
                    <td>
                        <i class="fa-solid fa-calendar-day text-muted mr-1"></i> {{ $d->incident_date->format('d M Y') }}
                    </td>
                    <td>
                        <small class="d-block">{{ $d->recordedBy->name ?? 'Officer' }}</small>
                    </td>
                    <td class="text-center">
                        @if($d->proof_file_path)
                            @php
                                $ext = strtolower(pathinfo($d->proof_file_path, PATHINFO_EXTENSION));
                            @endphp
                            @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                                <a href="{{ asset('storage/' . $d->proof_file_path) }}" target="_blank" class="btn btn-xs btn-outline-danger" title="View Proof Photo">
                                    <i class="fa-solid fa-image mr-1"></i> View Photo
                                </a>
                            @else
                                <a href="{{ asset('storage/' . $d->proof_file_path) }}" target="_blank" class="btn btn-xs btn-outline-danger" title="Download Report PDF">
                                    <i class="fa-solid fa-file-pdf mr-1"></i> View Report
                                </a>
                            @endif
                        @else
                            <span class="text-muted small">No File</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="py-4">
                            <div class="avatar-icon-wrapper rounded-circle bg-light text-danger p-3 mb-3 mx-auto" style="width: 70px; height: 70px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-1">No Damaged or Wasted Stock Incidents</h5>
                            <p class="text-muted small mb-3" style="max-width: 460px; margin: 0 auto;">
                                Report spoiled, transit-damaged, or ruptured stock bags with mandatory photo or surveyor report evidence to keep inventory accurate.
                            </p>
                            <button type="button" class="btn btn-danger btn-lg shadow font-weight-bold px-4" onclick="openDamageModal()" data-toggle="modal" data-target="#createDamageModal">
                                <i class="fa-solid fa-plus-circle mr-2"></i> Record Damaged Stock Now
                            </button>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing {{ $damages->firstItem() ?? 0 }} to {{ $damages->lastItem() ?? 0 }} of {{ $damages->total() }}</span>
        {{ $damages->links() }}
    </div>
</div>
@endsection

@push('modals')
{{-- Record Damage Modal --}}
<div class="modal fade" id="createDamageModal" tabindex="-1" role="dialog" aria-labelledby="createDamageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="POST" action="{{ route('admin.stock.damages.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold" id="createDamageModalTitle">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Record Wasted / Damaged Stock Incident
                    </h5>
                    <button type="button" class="close text-white" onclick="closeDamageModal()" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-warning py-2 small text-dark">
                        <i class="fa-solid fa-camera mr-1"></i> <strong>Mandatory Evidence:</strong> An inspection photo or official surveyor damage report is mandatory. The entered quantity will be immediately deducted from the depot inventory.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Collection Center *</label>
                            <select name="collection_center_id" class="form-control" required>
                                <option value="">Select Depot / Center</option>
                                @foreach($centers as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->name }} (Available: {{ number_format($c->inventories->sum('current_balance')) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold text-dark">Scheme Stock Item *</label>
                            <select name="scheme_stock_item_id" class="form-control" required>
                                <option value="">Select Scheme Item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->item_name }} ({{ $item->unit }}) - {{ $item->scheme->title ?? '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Quantity Damaged / Wasted *</label>
                            <input type="number" step="any" min="0.01" name="quantity" class="form-control font-weight-bold text-danger" placeholder="e.g. 15" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Damage Type *</label>
                            <select name="damage_type" class="form-control" required>
                                <option value="packaging_tear">Packaging Tear / Rupture</option>
                                <option value="water_moisture">Water / Moisture Infiltration</option>
                                <option value="pest_infestation">Pest / Rodent Infestation</option>
                                <option value="spoilage">Seed Spoilage / Rotting</option>
                                <option value="transit_breakage">Transit Road Damage</option>
                                <option value="expiry">Expired Germination Window</option>
                                <option value="other">Other Damage</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold text-dark">Incident Date *</label>
                            <input type="date" name="incident_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Incident Proof / Evidence (Photo or Report) *</label>
                        <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small class="text-muted">Upload high-resolution photo showing damaged packaging or certified surveyor report (Max: 10MB)</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-dark">Detailed Reason & Description *</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Describe the cause of damage in detail, e.g. Roof leak during flash floods caused water soaking of 15 seed bags in warehouse sector B..." required minlength="10"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Immediate Action Taken</label>
                        <input type="text" name="action_taken" class="form-control" placeholder="e.g. Quarantined in segregated storage bay, Deputy Director notified, insurance report filed">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" onclick="closeDamageModal()" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4 shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i> Record & Deduct from Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openDamageModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createDamageModal').modal('show');
    } else {
        var m = document.getElementById('createDamageModal');
        if (m) {
            m.classList.add('show');
            m.style.display = 'block';
            document.body.classList.add('modal-open');
            if (!document.getElementById('damageModalBackdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'damageModalBackdrop';
                backdrop.onclick = closeDamageModal;
                document.body.appendChild(backdrop);
            }
        }
    }
}

function closeDamageModal() {
    if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        $('#createDamageModal').modal('hide');
    } else {
        var m = document.getElementById('createDamageModal');
        if (m) {
            m.classList.remove('show');
            m.style.display = 'none';
            document.body.classList.remove('modal-open');
            var backdrop = document.getElementById('damageModalBackdrop');
            if (backdrop) backdrop.remove();
        }
    }
}
</script>
@endpush

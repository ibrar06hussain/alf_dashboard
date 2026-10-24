@extends('layouts.admin')

@section('content')
<div class="app-page-title">
    <div class="page-title-wrapper">
        <div class="page-title-heading">
            <div class="page-title-icon bg-danger text-white">
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
            <button type="button" class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#createDamageModal">
                <i class="fa-solid fa-plus-circle mr-1"></i> Record Damaged Stock
            </button>
        </div>
    </div>
</div>

{{-- Damages Table --}}
<div class="main-card mb-4 card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="font-weight-bold text-dark"><i class="fa-solid fa-dumpster-fire mr-2 text-danger"></i> Damaged & Wasted Stock Audit Log ({{ $damages->total() }})</span>
        <span class="badge badge-light border text-danger">All entries automatically deducted from center balance</span>
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
                    <td colspan="9" class="text-center text-muted py-4">No damaged or wasted stock incidents recorded.</td>
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

{{-- Record Damage Modal --}}
<div class="modal fade" id="createDamageModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.stock.damages.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i> Record Wasted / Damaged Stock Incident</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="fa-solid fa-camera mr-1"></i> <strong>Mandatory Evidence:</strong> An inspection photo or official surveyor damage report is mandatory. The entered quantity will be immediately deducted from the depot inventory.
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Collection Center *</label>
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
                            <label class="font-weight-bold">Scheme Stock Item *</label>
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
                            <label class="font-weight-bold">Quantity Damaged / Wasted *</label>
                            <input type="number" step="any" min="0.01" name="quantity" class="form-control font-weight-bold text-danger" placeholder="e.g. 15" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">Damage Type *</label>
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
                            <label class="font-weight-bold">Incident Date *</label>
                            <input type="date" name="incident_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold text-danger">Incident Proof / Evidence (Photo or Report) *</label>
                        <input type="file" name="proof_file" class="form-control-file" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small class="text-muted">Upload high-resolution photo showing damaged packaging or certified surveyor report (Max: 10MB)</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Detailed Reason & Description *</label>
                        <textarea name="reason" rows="3" class="form-control" placeholder="Describe the cause of damage in detail, e.g. Roof leak during flash floods caused water soaking of 15 seed bags in warehouse sector B..." required minlength="10"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Immediate Action Taken</label>
                        <input type="text" name="action_taken" class="form-control" placeholder="e.g. Quarantined in segregated storage bay, Deputy Director notified, insurance report filed">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger font-weight-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Record & Deduct from Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

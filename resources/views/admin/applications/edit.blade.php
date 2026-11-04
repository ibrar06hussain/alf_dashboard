@extends('layouts.admin')

@section('title', 'Edit Application ' . $application->application_no)

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Modify Application Data: ' . $application->application_no,
    'subtitle' => 'Update applicant biodata, scheme variables, and dynamic form fields. Every modification is immutably logged with before/after snapshots, user identity, and timestamp.',
    'icon' => 'fa-solid fa-file-pen',
    'actions' => [
        ['label' => 'View Application', 'url' => route('admin.applications.show', $application), 'class' => 'btn btn-outline-secondary'],
        ['label' => 'Applications List', 'url' => route('admin.applications.index'), 'class' => 'btn btn-light'],
    ],
])

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-info me-1"></i> {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Please correct the validation errors:</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.applications.update', $application) }}" id="editApplicationForm">
    @csrf
    @method('PUT')

    <div class="row g-3">
        {{-- LEFT COLUMN: Applicant Biodata & Scheme Quantities --}}
        <div class="col-lg-8">
            {{-- 1. Applicant Biodata --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-user-pen text-primary me-2"></i> Applicant Biodata & Contact Details
                    </h6>
                    <span class="badge bg-secondary font-monospace">CNIC: {{ $application->applicant?->cnic }} (Locked)</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control form-control-sm @error('full_name') is-invalid @enderror"
                                value="{{ old('full_name', $application->applicant?->full_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Father / Husband Name</label>
                            <input type="text" name="father_name" class="form-control form-control-sm @error('father_name') is-invalid @enderror"
                                value="{{ old('father_name', $application->applicant?->father_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Mobile / Contact Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                value="{{ old('phone', $application->applicant?->phone) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                                value="{{ old('email', $application->applicant?->email) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Gender</label>
                            <select name="gender" class="form-select form-select-sm">
                                <option value="male" @selected(old('gender', $application->applicant?->gender) === 'male')>Male</option>
                                <option value="female" @selected(old('gender', $application->applicant?->gender) === 'female')>Female</option>
                                <option value="other" @selected(old('gender', $application->applicant?->gender) === 'other')>Other</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">District <span class="text-danger">*</span></label>
                            <select name="district_id" class="form-select form-select-sm @error('district_id') is-invalid @enderror" required>
                                @foreach($districts as $d)
                                    <option value="{{ $d->id }}" @selected(old('district_id', $application->district_id) == $d->id)>
                                        {{ $d->name }} ({{ $d->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tehsil</label>
                            <input type="text" name="tehsil" class="form-control form-control-sm"
                                value="{{ old('tehsil', $application->applicant?->tehsil) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Union Council</label>
                            <input type="text" name="union_council" class="form-control form-control-sm"
                                value="{{ old('union_council', $application->applicant?->union_council) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Village / Mohallah</label>
                            <input type="text" name="village" class="form-control form-control-sm"
                                value="{{ old('village', $application->applicant?->village) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Residential Address</label>
                            <input type="text" name="address" class="form-control form-control-sm"
                                value="{{ old('address', $application->applicant?->address) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Scheme Quantities, Challan & Notes --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-boxes-stacked text-success me-2"></i> Scheme Demands, Quota &amp; Challan Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Demanded Quantity</label>
                            <input type="number" step="0.01" min="0.01" name="demanded_quantity" class="form-control form-control-sm"
                                value="{{ old('demanded_quantity', $application->demanded_quantity) }}">
                            <div class="form-text" style="font-size: 11px;">Units requested by farmer</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Approved Quantity</label>
                            <input type="number" step="0.01" min="0" name="approved_quantity" class="form-control form-control-sm"
                                value="{{ old('approved_quantity', $application->approved_quantity) }}">
                            <div class="form-text" style="font-size: 11px;">Authorized by Deputy Director</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Allocated Quantity</label>
                            <input type="number" step="1" min="0" name="allocated_quantity" class="form-control form-control-sm"
                                value="{{ old('allocated_quantity', $application->allocated_quantity) }}">
                            <div class="form-text" style="font-size: 11px;">Bags allocated in warehouse</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Distributed Quantity</label>
                            <input type="number" step="1" min="0" name="distributed_quantity" class="form-control form-control-sm"
                                value="{{ old('distributed_quantity', $application->distributed_quantity) }}">
                            <div class="form-text" style="font-size: 11px;">Physically disbursed to farmer</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Challan Fee (Integer Amount, PKR)</label>
                            <input type="number" step="1" min="0" name="challan_amount" class="form-control form-control-sm"
                                value="{{ old('challan_amount', $application->challan_amount ? (int) ceil((float) $application->challan_amount) : '') }}">
                            <div class="form-text" style="font-size: 11px;">Nearest upper integer amount for fee deposit</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Challan Due Date</label>
                            <input type="date" name="challan_due_date" class="form-control form-control-sm"
                                value="{{ old('challan_due_date', $application->challan_due_date?->format('Y-m-d')) }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Officer Verification / Internal Note</label>
                            <textarea name="verification_note" class="form-control form-control-sm" rows="2">{{ old('verification_note', $application->verification_note) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Dynamic Form Field Values (Scheme-Specific) --}}
            @if($formFields->isNotEmpty())
                <div class="card alf-panel shadow-sm mb-3">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark">
                            <i class="fa-solid fa-list-check text-info me-2"></i> Dynamic Scheme Form Questions &amp; Answers
                        </h6>
                        <span class="badge bg-light text-dark border">{{ $formFields->count() }} Form Questions</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($formFields as $field)
                                @php
                                    $fieldRecord = $fieldValuesKeyed->get($field->id);
                                    $currentVal = $fieldRecord?->value ?? '';
                                    $fieldName = "fields[{$field->id}]";
                                @endphp
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">
                                        {{ $field->field_label }}
                                        @if($field->is_required) <span class="text-danger">*</span> @endif
                                    </label>

                                    @if($field->field_type === 'textarea')
                                        <textarea name="{{ $fieldName }}" class="form-control form-control-sm" rows="2">{{ old("fields.{$field->id}", $currentVal) }}</textarea>
                                    @elseif($field->field_type === 'select')
                                        <select name="{{ $fieldName }}" class="form-select form-select-sm">
                                            <option value="">-- Select --</option>
                                            @foreach($field->options ?? [] as $opt)
                                                @php $optVal = is_array($opt) ? ($opt['value'] ?? $opt['label']) : $opt; @endphp
                                                <option value="{{ $optVal }}" @selected(old("fields.{$field->id}", $currentVal) == $optVal)>
                                                    {{ is_array($opt) ? ($opt['label'] ?? $opt['value']) : $opt }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @elseif($field->field_type === 'number')
                                        <input type="number" step="any" name="{{ $fieldName }}" class="form-control form-control-sm"
                                            value="{{ old("fields.{$field->id}", $currentVal) }}">
                                    @elseif($field->field_type === 'date')
                                        <input type="date" name="{{ $fieldName }}" class="form-control form-control-sm"
                                            value="{{ old("fields.{$field->id}", $currentVal) }}">
                                    @else
                                        <input type="text" name="{{ $fieldName }}" class="form-control form-control-sm"
                                            value="{{ old("fields.{$field->id}", $currentVal) }}">
                                    @endif

                                    @if($field->help_text)
                                        <div class="form-text" style="font-size: 11px;">{{ $field->help_text }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT COLUMN: Mandatory Modification Reason + Audit Trail --}}
        <div class="col-lg-4">
            {{-- Mandatory Modification Reason & Submit --}}
            <div class="card alf-panel shadow-sm mb-3 border-top border-3 border-danger sticky-top" style="top: 85px; z-index: 10;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-danger">
                        <i class="fa-solid fa-clipboard-check me-2"></i> Modification Justification (Mandatory)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason for Modification <span class="text-danger">*</span></label>
                        <textarea name="modification_reason" class="form-control form-control-sm @error('modification_reason') is-invalid @enderror"
                            rows="3" placeholder="Provide a detailed official justification for modifying this application data..." required>{{ old('modification_reason') }}</textarea>
                        <div class="form-text" style="font-size: 11px;">
                            This reason, your identity (<strong>{{ auth()->user()->name }}</strong>), IP address (<code>{{ request()->ip() }}</code>), and current/updated values will be saved immutably in the audit log.
                        </div>
                    </div>

                    <div class="p-2 rounded bg-light border small mb-3">
                        <div><strong>Target Application:</strong> <code>{{ $application->application_no }}</code></div>
                        <div><strong>Scheme:</strong> {{ $application->scheme?->title ?? '—' }}</div>
                        <div><strong>Current Stage:</strong> <span class="badge bg-primary">{{ $application->statusLabel() }}</span></div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100 fw-bold py-2" onclick="return confirm('Confirm updating this application? An immutable audit log snapshot will be recorded with your user credentials.');">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes &amp; Record Audit Log
                    </button>
                    <a href="{{ route('admin.applications.show', $application) }}" class="btn btn-outline-secondary w-100 btn-sm mt-2">
                        Cancel &amp; Return
                    </a>
                </div>
            </div>

            {{-- Previous Modification History for this Application --}}
            <div class="card alf-panel shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> Modification Audit History
                    </h6>
                    <span class="badge bg-secondary">{{ $application->editAuditLogs->count() }} Edits</span>
                </div>
                <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                    @forelse($application->editAuditLogs as $log)
                        <div class="p-3 border-bottom small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="text-dark">{{ $log->user_name ?: ($log->user?->name ?? 'System Admin') }}</strong>
                                <span class="text-muted" style="font-size: 11px;">{{ $log->modified_at?->format('d M Y, H:i') }}</span>
                            </div>
                            <div class="text-muted mb-2" style="font-size: 11px;">
                                <span class="badge bg-light text-dark border">{{ $log->user_role ?: 'Admin' }}</span>
                                <span class="ms-1">IP: <code>{{ $log->ip_address ?: '—' }}</code></span>
                            </div>
                            <div class="p-2 rounded bg-light border mb-2">
                                <strong>Reason:</strong> <em>{{ $log->modification_reason ?: 'No justification recorded' }}</em>
                            </div>
                            <div>
                                <span class="text-muted fw-bold" style="font-size: 11px; text-transform: uppercase;">Updated Fields ({{ count($log->changed_fields ?? []) }}):</span>
                                <div class="mt-1">
                                    @foreach($log->changed_fields ?? [] as $fld)
                                        @php
                                            $oldV = $log->old_values[$fld]['value'] ?? ($log->old_values[$fld] ?? '—');
                                            $newV = $log->new_values[$fld]['value'] ?? ($log->new_values[$fld] ?? '—');
                                            $label = $log->new_values[$fld]['label'] ?? $fld;
                                        @endphp
                                        <div class="p-1 mb-1 rounded border bg-white" style="font-size: 11px;">
                                            <strong class="text-primary">{{ $label }}:</strong>
                                            <div class="text-danger text-truncate">Old: <del>{{ $oldV !== '' ? $oldV : '(empty)' }}</del></div>
                                            <div class="text-success text-truncate">New: <strong>{{ $newV !== '' ? $newV : '(empty)' }}</strong></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted small">
                            <i class="fa-solid fa-clock-rotate-left fs-3 opacity-50 mb-2 d-block"></i>
                            No modifications have been recorded for this application yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</form>
@endsection


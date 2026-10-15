@extends('layouts.admin')

@section('title', $application->application_no)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $application->application_no,
    'subtitle' => ($application->scheme?->title ?? '—').' · '.( \App\Models\Application::STATUSES[$application->status] ?? $application->status),
    'icon' => 'fa-solid fa-id-card',
    'actions' => [
        ['label' => 'Back to list', 'url' => route('admin.applications.index'), 'class' => 'btn btn-light'],
    ],
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Status', 'value' => \App\Models\Application::STATUSES[$application->status] ?? $application->status, 'icon' => 'fa-solid fa-flag', 'tone' => 'amber'],
    ['label' => 'District', 'value' => $application->district?->name ?? '—', 'icon' => 'fa-solid fa-location-dot', 'tone' => 'blue'],
    ['label' => 'Documents', 'value' => $application->documents->count(), 'icon' => 'fa-solid fa-paperclip', 'tone' => 'green'],
    ['label' => 'Submitted', 'value' => $application->submitted_at?->format('d M Y') ?: $application->created_at?->format('d M Y') ?: '—', 'icon' => 'fa-solid fa-calendar', 'tone' => 'slate'],
]])

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">Applicant</div>
            <div class="card-body">
                <div class="alf-meta-grid">
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Name</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->full_name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Father / Husband</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->father_name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">CNIC</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->cnic }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Phone</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->phone }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">District</span>
                        <span class="alf-meta-item__value">{{ $application->district?->name }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Tehsil</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->tehsil ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Union Council</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->union_council ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Village</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->village ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item" style="grid-column:1/-1">
                        <span class="alf-meta-item__label">Land location</span>
                        <span class="alf-meta-item__value">{{ $application->applicant?->land_location }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card alf-panel mb-3">
            <div class="card-header">Form Field Values</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table alf-table mb-0">
                        <tbody>
                        @forelse($application->fieldValues as $value)
                            <tr>
                                <th style="width:40%">
                                    {{ $value->formField?->field_label }}
                                    @if($value->formField?->field_label_ur)
                                        <div class="small text-muted" dir="rtl" style="font-family:'Noto Nastaliq Urdu', Tahoma, sans-serif;">{{ $value->formField->field_label_ur }}</div>
                                    @endif
                                </th>
                                <td>
                                    @if($value->formField?->field_type === 'checkbox_with_text')
                                        @if(!empty($value->value))
                                            @php
                                                $parts = array_filter(array_map('trim', explode(';', $value->value)));
                                            @endphp
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($parts as $part)
                                                    <div>
                                                        <span class="badge bg-success-subtle text-success me-1"><i class="fa-solid fa-check me-1"></i>Selected</span>
                                                        @if(str_contains($part, ':'))
                                                            @php [$pLabel, $pText] = array_map('trim', explode(':', $part, 2)); @endphp
                                                            <strong class="text-dark">{{ $pLabel }}:</strong>
                                                            <span class="text-secondary">{{ $pText }}</span>
                                                        @else
                                                            <strong class="text-dark">{{ $part }}</strong>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="badge bg-light text-muted border">None Selected / Unchecked</span>
                                        @endif
                                    @else
                                        {{ is_string($value->value) ? $value->value : json_encode($value->value) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted p-3">No field values recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card alf-panel mb-3">
            <div class="card-header">Documents</div>
            <ul class="list-group list-group-flush">
                @forelse($application->documents as $doc)
                    @php
                        $ext = strtolower(pathinfo($doc->original_name ?: $doc->file_path, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
                        $fileUrl = $doc->file_path ? asset('storage/'.$doc->file_path) : null;
                    @endphp
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $doc->formDocument?->document_name }}</div>
                                <div class="small text-muted">{{ $doc->original_name }}</div>
                                @if($isImage && $fileUrl)
                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="d-inline-block mt-2">
                                        <img src="{{ $fileUrl }}" alt="{{ $doc->original_name }}" class="border rounded" style="max-width:220px;max-height:140px;object-fit:cover;">
                                    </a>
                                @endif
                            </div>
                            <a href="{{ route('admin.applications.documents.download', [$application, $doc]) }}" class="btn btn-sm btn-outline-primary">Download</a>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No documents uploaded.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        @if(!empty($quotaStatus))
            <div class="card alf-panel mb-3">
                <div class="card-header">District Quota</div>
                <div class="card-body">
                    <div class="h4 mb-1">{{ $quotaStatus['used'] }} / {{ $quotaStatus['quota'] }}</div>
                    <div class="small text-muted mb-2">Approved / Disbursed vs quota for {{ $application->district?->name }}</div>
                    <div class="progress" style="height:8px;">
                        @php $pct = $quotaStatus['quota'] > 0 ? min(100, round(($quotaStatus['used'] / $quotaStatus['quota']) * 100)) : 0; @endphp
                        <div class="progress-bar {{ $quotaStatus['exceeded'] ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                    </div>
                    @if($quotaStatus['exceeded'])
                        <div class="text-danger small mt-2">Quota full — further approvals blocked.</div>
                    @endif
                </div>
            </div>
        @endif

        @if(count($allowedTransitions))
            <div class="card alf-panel mb-3">
                <div class="card-header">Update Status</div>
                <div class="card-body alf-form-grid">
                    <form method="POST" action="{{ route('admin.applications.status', $application) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">New status</label>
                            <select name="status" class="form-control" required id="status-select">
                                @foreach($allowedTransitions as $status)
                                    <option value="{{ $status }}">{{ \App\Models\Application::STATUSES[$status] ?? $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification note</label>
                            <textarea name="verification_note" class="form-control" rows="2">{{ old('verification_note', $application->verification_note) }}</textarea>
                        </div>
                        <div class="mb-3" id="rejection-box">
                            <label class="form-label">Rejection reason</label>
                            <textarea name="rejection_reason" class="form-control" rows="2">{{ old('rejection_reason', $application->rejection_reason) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Log note</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100">Update Status</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="card alf-panel mb-3">
            <div class="card-header">Status Log</div>
            <ul class="list-group list-group-flush">
                @forelse($application->statusLogs as $log)
                    <li class="list-group-item">
                        <div><strong>{{ $log->from_status ?: '—' }} → {{ $log->to_status }}</strong></div>
                        <div class="small text-muted">{{ $log->user?->name }} · {{ $log->created_at?->format('d M Y H:i') }}</div>
                        @if($log->note)<div class="small">{{ $log->note }}</div>@endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">No status changes yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('status-select');
    const box = document.getElementById('rejection-box');
    if (!select || !box) return;
    const toggle = () => { box.style.display = select.value === 'rejected' ? '' : 'none'; };
    select.addEventListener('change', toggle);
    toggle();
});
</script>
@endpush

@extends('layouts.admin')

@section('title', $scheme->title)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $scheme->title,
    'subtitle' => ($scheme->department?->name ?? '—').' · '.$scheme->typeLabel().' · '.strtoupper($scheme->scope),
    'icon' => 'fa-solid fa-file-lines',
    'actions' => array_values(array_filter([
        ['label' => 'Edit', 'url' => route('admin.schemes.edit', $scheme), 'class' => 'btn btn-primary'],
        $scheme->type === 'running'
            ? ['label' => 'Form Builder', 'url' => route('admin.form-builder.edit', $scheme), 'class' => 'btn btn-info']
            : null,
        ['label' => 'Back', 'url' => route('admin.schemes.index', $scheme->type === 'proposed' ? ['type' => 'proposed'] : []), 'class' => 'btn btn-light'],
    ])),
])

@include('admin.partials.stats', ['stats' => [
    ['label' => 'Status', 'value' => ucfirst($scheme->status), 'icon' => 'fa-solid fa-circle-info', 'tone' => 'slate'],
    ['label' => 'Budget (M)', 'value' => $scheme->budget ? number_format($scheme->budget / 1000000, 1) : '—', 'icon' => 'fa-solid fa-money-bill', 'tone' => 'green'],
    ['label' => 'Applications', 'value' => $scheme->applications()->count(), 'icon' => 'fa-solid fa-folder', 'tone' => 'blue'],
    ['label' => 'Interests', 'value' => $scheme->interests()->count(), 'icon' => 'fa-solid fa-bell', 'tone' => 'amber'],
]])

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card alf-panel mb-3">
            <div class="card-header">Overview</div>
            <div class="card-body">
                <div class="alf-meta-grid mb-3">
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Type</span>
                        <span class="alf-meta-item__value">{{ $scheme->typeLabel() }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Scope</span>
                        <span class="alf-meta-item__value">{{ strtoupper($scheme->scope) }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Start date</span>
                        <span class="alf-meta-item__value">{{ $scheme->start_date?->format('d M Y') ?: '—' }}</span>
                    </div>
                    <div class="alf-meta-item">
                        <span class="alf-meta-item__label">Last date</span>
                        <span class="alf-meta-item__value">{{ $scheme->last_date?->format('d M Y') ?: '—' }}</span>
                    </div>
                </div>
                <h5 class="fw-bold">Description</h5>
                <p style="white-space:pre-wrap;">{{ $scheme->description ?: '—' }}</p>
                <h5 class="fw-bold">Eligibility</h5>
                <p style="white-space:pre-wrap;">{{ $scheme->eligibility_criteria ?: '—' }}</p>
                <h5 class="fw-bold">Terms &amp; conditions</h5>
                <p class="mb-0" style="white-space:pre-wrap;">{{ $scheme->terms_conditions ?: '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card alf-panel mb-3">
            <div class="card-header">Cover image</div>
            <div class="card-body p-2">
                <div class="alf-show-cover-wrap">
                    <img src="{{ $scheme->imageUrl() }}" alt="{{ $scheme->title }}" class="alf-show-cover">
                </div>
                <div class="small text-muted mt-2 px-1">
                    @if($scheme->featured_image)
                        Uploaded cover image
                    @else
                        Default / fallback image (no custom upload yet)
                    @endif
                </div>
            </div>
        </div>
        <div class="card alf-panel mb-3">
            <div class="card-header">District Quotas</div>
            <ul class="list-group list-group-flush">
                @forelse($scheme->schemeDistricts as $sd)
                    @php $usage = $quotaUsage[$sd->district_id] ?? null; @endphp
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span>{{ $sd->district?->name }}</span>
                            <strong>{{ $usage['used'] ?? 0 }} / {{ $sd->quota }}</strong>
                        </div>
                        @if($usage)
                            @php $pct = $sd->quota > 0 ? min(100, round(($usage['used'] / $sd->quota) * 100)) : 0; @endphp
                            <div class="progress mt-2" style="height:6px;">
                                <div class="progress-bar {{ ($usage['exceeded'] ?? false) ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">No district quotas (GB scope)</li>
                @endforelse
            </ul>
        </div>
        <div class="card alf-panel mb-3">
            <div class="card-header">Forms</div>
            <ul class="list-group list-group-flush">
                @forelse($scheme->forms as $form)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>v{{ $form->version }} — {{ $form->title }}</span>
                        <span class="alf-badge alf-badge--{{ $form->is_published ? 'green' : 'slate' }}">
                            {{ $form->is_published ? 'Published' : 'Draft' }}
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No application form (typical for proposed schemes).</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

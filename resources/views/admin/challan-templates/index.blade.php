@extends('layouts.admin')

@section('title', 'Challan Templates')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'Challan Templates',
    'subtitle' => 'Configure dynamic bank deposit challan templates, treasury account details, fee formulas, and assign them to department schemes.',
    'icon' => 'fa-solid fa-receipt',
    'actions' => [
        ['label' => 'Create Challan Template', 'url' => route('admin.challan-templates.create'), 'class' => 'btn btn-primary'],
    ],
])

@php
    $pageStats = [
        ['label' => 'Total Templates', 'value' => $templates->total(), 'icon' => 'fa-solid fa-layer-group', 'tone' => 'blue'],
        ['label' => 'Active Templates', 'value' => $templates->where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'green'],
        ['label' => 'Default Template', 'value' => $templates->where('is_default', true)->first()?->name ?? 'None', 'icon' => 'fa-solid fa-star', 'tone' => 'amber'],
        ['label' => 'Total Schemes', 'value' => $totalSchemes, 'icon' => 'fa-solid fa-file-invoice-dollar', 'tone' => 'teal'],
    ];
@endphp

@include('admin.partials.stats', ['stats' => $pageStats])

<div class="card alf-panel mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-receipt text-primary me-2"></i> All Dynamic Challan Templates
        </h5>
        <a href="{{ route('admin.challan-templates.create') }}" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> New Template
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-3">Template Name & Header</th>
                        <th>Bank & Account Details</th>
                        <th>Validity & Directions</th>
                        <th>Fee Mode & Sharing</th>
                        <th>Assigned Schemes</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center">
                                    <div class="me-2 text-primary fs-4">
                                        <i class="fa-solid fa-file-invoice"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                            <span>{{ $template->name }}</span>
                                            @if($template->is_default)
                                                <span class="badge bg-warning text-dark border"><i class="fa-solid fa-star me-1"></i>Default</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted">{{ $template->department_name }}</div>
                                        <div class="text-xs text-secondary">{{ $template->challan_title }} | {{ $template->sub_title }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <div class="fw-semibold text-dark"><i class="fa-solid fa-building-columns text-secondary me-1"></i>{{ $template->bank_name }}</div>
                                    <div class="text-muted"><span class="text-secondary fw-semibold">A/C:</span> {{ $template->account_no }}</div>
                                    <div class="text-muted text-truncate" style="max-width: 220px;" title="{{ $template->account_title }}">{{ $template->account_title }}</div>
                                    <div class="badge bg-light text-dark border mt-1">{{ $template->account_head }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mb-1">
                                        <i class="fa-regular fa-clock me-1"></i> {{ $template->validity_days }} Days Validity
                                    </span>
                                    <p class="text-muted mb-0 text-truncate" style="max-width: 240px;" title="{{ $template->directions }}">
                                        {{ $template->directions }}
                                    </p>
                                </div>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle mb-1 text-capitalize">
                                        <i class="fa-solid fa-calculator me-1"></i> {{ str_replace('_', ' ', $template->fee_mode) }}
                                    </span>
                                    <div class="text-muted">
                                        Govt: <strong>{{ (int) $template->govt_share_pct }}%</strong> | Farmer: <strong>{{ (int) $template->farmer_share_pct }}%</strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($template->schemes->count() > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($template->schemes as $s)
                                            <a href="{{ route('admin.schemes.edit', $s) }}" class="badge bg-light text-dark border text-decoration-none">
                                                <i class="fa-solid fa-link me-1 text-primary"></i>{{ \Illuminate\Support\Str::limit($s->title, 24) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic">No schemes linked</span>
                                @endif
                            </td>
                            <td>
                                @if($template->is_active)
                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('admin.challan-templates.preview', $template) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Preview PDF Challan">
                                    <i class="fa-solid fa-eye me-1"></i> Preview
                                </a>
                                <a href="{{ route('admin.challan-templates.edit', $template) }}" class="btn btn-sm btn-outline-primary" title="Edit Template">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                @if(! $template->is_default)
                                    <form action="{{ route('admin.challan-templates.destroy', $template) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this challan template? Linked schemes will use the default template.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Template">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-file-circle-question fs-2 d-block mb-2"></i>
                                No challan templates found. Click "Create Challan Template" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($templates->hasPages())
        <div class="card-footer bg-white border-top py-3">
            {{ $templates->links() }}
        </div>
    @endif
</div>
@endsection

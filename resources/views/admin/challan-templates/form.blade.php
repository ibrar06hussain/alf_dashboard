@extends('layouts.admin')

@php
    $isEdit = $template->exists;
    $title = $isEdit ? "Edit Challan Template: {$template->name}" : "Create New Challan Template";
@endphp

@section('title', $title)

@section('content')
@include('admin.partials.page-hero', [
    'title' => $title,
    'subtitle' => 'Configure dynamic bank challan deposit layout, government account details, validity rules, and link to schemes.',
    'icon' => 'fa-solid fa-receipt',
    'actions' => [
        ['label' => 'Back to Templates', 'url' => route('admin.challan-templates.index'), 'class' => 'btn btn-outline-secondary'],
        $isEdit ? ['label' => 'Preview PDF', 'url' => route('admin.challan-templates.preview', $template), 'class' => 'btn btn-info', 'target' => '_blank'] : null,
    ],
])

<form action="{{ $isEdit ? route('admin.challan-templates.update', $template) : route('admin.challan-templates.store') }}" method="POST">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
        {{-- Section 1: Template Info & Branding --}}
        <div class="col-lg-8">
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-stamp text-primary me-2"></i> Department Branding & Header
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $template->name) }}" placeholder="e.g. Wheat Seed Subsidized Deposit Challan" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Administrative title for identifying this template in the system.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Department Name (Printed on Challan Header) <span class="text-danger">*</span></label>
                            <input type="text" name="department_name" class="form-control @error('department_name') is-invalid @enderror"
                                value="{{ old('department_name', $template->department_name ?: 'Agriculture LiveStock and Fishries Department Gilgit Baltistan') }}" required>
                            @error('department_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Government Subtitle</label>
                            <input type="text" name="sub_title" class="form-control @error('sub_title') is-invalid @enderror"
                                value="{{ old('sub_title', $template->sub_title ?: 'Government of Gilgit-Baltistan') }}">
                            @error('sub_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Challan Document Title <span class="text-danger">*</span></label>
                            <input type="text" name="challan_title" class="form-control @error('challan_title') is-invalid @enderror"
                                value="{{ old('challan_title', $template->challan_title ?: 'FEE DEPOSIT CHALLAN') }}" required>
                            @error('challan_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 2: Bank & Treasury Details --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-building-columns text-success me-2"></i> Designated Bank & Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                                value="{{ old('bank_name', $template->bank_name ?: 'National Bank Of Pakistan') }}" required>
                            @error('bank_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Number (A/C No) <span class="text-danger">*</span></label>
                            <input type="text" name="account_no" class="form-control @error('account_no') is-invalid @enderror"
                                value="{{ old('account_no', $template->account_no ?: '4256260305') }}" required>
                            @error('account_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Title <span class="text-danger">*</span></label>
                            <input type="text" name="account_title" class="form-control @error('account_title') is-invalid @enderror"
                                value="{{ old('account_title', $template->account_title ?: 'Agriculture LiveStock and Fishries Department Gilgit Baltistan') }}" required>
                            @error('account_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Account Head / Purpose <span class="text-danger">*</span></label>
                            <input type="text" name="account_head" class="form-control @error('account_head') is-invalid @enderror"
                                value="{{ old('account_head', $template->account_head ?: 'Application Processing Fee(C03870)') }}" required>
                            @error('account_head')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 3: Validity & Directions --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-clock-rotate-left text-warning me-2"></i> Validity & Applicant Directions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Challan Validity (Days) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" min="1" max="365" name="validity_days" class="form-control @error('validity_days') is-invalid @enderror"
                                    value="{{ old('validity_days', $template->validity_days ?: 10) }}" required>
                                <span class="input-group-text">Days</span>
                            </div>
                            @error('validity_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Default: 10 days after generation date.</div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Fee Calculation Mode <span class="text-danger">*</span></label>
                            <select name="fee_mode" class="form-select @error('fee_mode') is-invalid @enderror" required>
                                <option value="seed_configured" @selected(old('fee_mode', $template->fee_mode) === 'seed_configured')>
                                    Seed Configured Data (District delivered bag rate * approved bags with subsidy share)
                                </option>
                                <option value="fixed_unit" @selected(old('fee_mode', $template->fee_mode) === 'fixed_unit')>
                                    Scheme Fixed Rate per Unit
                                </option>
                                <option value="fixed_total" @selected(old('fee_mode', $template->fee_mode) === 'fixed_total')>
                                    Fixed Flat Fee
                                </option>
                                <option value="free" @selected(old('fee_mode', $template->fee_mode) === 'free')>
                                    Fee Free / 100% Subsidized
                                </option>
                            </select>
                            @error('fee_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Government Subsidy Share (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="govt_share_pct" class="form-control @error('govt_share_pct') is-invalid @enderror"
                                    value="{{ old('govt_share_pct', $template->govt_share_pct ?? 50.00) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            @error('govt_share_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Farmer / Beneficiary Share (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="farmer_share_pct" class="form-control @error('farmer_share_pct') is-invalid @enderror"
                                    value="{{ old('farmer_share_pct', $template->farmer_share_pct ?? 50.00) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            @error('farmer_share_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold">Applicant Directions <span class="text-danger">*</span></label>
                            <textarea name="directions" rows="3" class="form-control @error('directions') is-invalid @enderror" required>{{ old('directions', $template->directions ?: 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.') }}</textarea>
                            @error('directions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text small">Printed directly at the footer of each challan copy.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4: Side Panel - Scheme Assignment & Settings --}}
        <div class="col-lg-4">
            {{-- Publish & Default Settings --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-sliders text-secondary me-2"></i> Template Status & Defaults
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default"
                            @checked(old('is_default', $template->is_default))>
                        <label class="form-check-label fw-bold" for="is_default">Default System Challan</label>
                        <div class="form-text small text-muted">Use as default template for all schemes without an explicit template.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            @checked(old('is_active', $template->is_active ?? true))>
                        <label class="form-check-label fw-bold" for="is_active">Template Active</label>
                        <div class="form-text small text-muted">Allow schemes to generate challans using this template.</div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="show_breakdown" value="1" id="show_breakdown"
                            @checked(old('show_breakdown', $template->show_breakdown ?? true))>
                        <label class="form-check-label fw-bold" for="show_breakdown">Show Breakdown Table</label>
                        <div class="form-text small text-muted">Print Cost of 1 bag, Approved bags, Total bill, Govt Share & Final Bill.</div>
                    </div>
                </div>
            </div>

            {{-- Assign to Schemes --}}
            <div class="card alf-panel shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-link text-primary me-2"></i> Assign to Schemes
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Check the schemes that should print this challan template whenever an applicant challan is generated:
                    </p>
                    <div style="max-height: 280px; overflow-y: auto;" class="border rounded p-2 bg-light">
                        @forelse($schemes as $s)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="schemes[]" value="{{ $s->id }}" id="scheme_{{ $s->id }}"
                                    @checked(in_array($s->id, old('schemes', $assignedSchemeIds)))>
                                <label class="form-check-label small" for="scheme_{{ $s->id }}">
                                    <strong>{{ $s->title }}</strong>
                                    <span class="d-block text-muted" style="font-size: 11px;">
                                        {{ $s->department?->name ?? 'ALF Department' }}
                                        @if($s->challan_template_id && $s->challan_template_id != $template->id)
                                            <span class="text-warning">(Assigned to other template)</span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                        @empty
                            <div class="small text-muted text-center py-2">No schemes found in the system.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card alf-panel shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 fw-bold mb-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> {{ $isEdit ? 'Update Template' : 'Save & Activate Template' }}
                    </button>
                    @if($isEdit)
                        <a href="{{ route('admin.challan-templates.preview', $template) }}" target="_blank" class="btn btn-outline-info w-100 mb-2">
                            <i class="fa-solid fa-eye me-1"></i> Preview PDF Sample
                        </a>
                    @endif
                    <a href="{{ route('admin.challan-templates.index') }}" class="btn btn-light border w-100">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

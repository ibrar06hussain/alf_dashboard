@extends('layouts.admin')

@section('title', 'WhatsApp Templates & Business API Gateway')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'WhatsApp Notifications & Business API Gateway',
    'subtitle' => 'Configure bilingual (English & Urdu) WhatsApp templates, API credentials, and send filtered bulk WhatsApp messages by District, Scheme, Tehsil, or Mobile Operator.',
    'icon' => 'fa-brands fa-whatsapp',
    'actions' => [
        ['label' => 'Create WhatsApp Template', 'url' => route('admin.whatsapp-templates.create'), 'class' => 'btn btn-success'],
    ],
])

@php
    $pageStats = [
        ['label' => 'WhatsApp Templates', 'value' => $stats['total_templates'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
        ['label' => 'Active Triggers', 'value' => $stats['active_templates'], 'icon' => 'fa-solid fa-bolt', 'tone' => 'green'],
        ['label' => 'Live WhatsApp Sent', 'value' => $stats['total_sent'], 'icon' => 'fa-brands fa-whatsapp', 'tone' => 'teal'],
        ['label' => 'Simulated / Failed', 'value' => $stats['total_simulated'] . ' / ' . $stats['total_failed'], 'icon' => 'fa-solid fa-clock-rotate-left', 'tone' => 'amber'],
    ];
@endphp

@include('admin.partials.stats', ['stats' => $pageStats])

{{-- WhatsApp Business API Configuration & Quick Test Row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-brands fa-whatsapp text-success me-2"></i> WhatsApp Business API Gateway Configuration
                    </h6>
                    <small class="text-muted">Supports Meta WhatsApp Cloud API (`graph.facebook.com/v20.0`), UltraMsg / WaAPI, or Custom HTTP Gateway</small>
                </div>
                <form action="{{ route('admin.whatsapp-templates.check-connection') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="fa-solid fa-plug-circle-check me-1"></i> Verify API Connection
                    </button>
                </form>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.whatsapp-templates.gateway.update') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">WhatsApp API Provider</label>
                            <select name="whatsapp_provider" class="form-select form-select-sm">
                                <option value="meta_cloud" @selected($gatewayConfig['provider'] === 'meta_cloud')>Meta WhatsApp Cloud API (Official)</option>
                                <option value="ultramsg" @selected($gatewayConfig['provider'] === 'ultramsg')>UltraMsg / WaAPI REST Gateway</option>
                                <option value="custom_http" @selected($gatewayConfig['provider'] === 'custom_http')>Custom HTTP / Webhook Gateway</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold small">API Base / Endpoint URL</label>
                            <input type="url" name="whatsapp_api_url" class="form-control form-control-sm"
                                value="{{ old('whatsapp_api_url', $gatewayConfig['api_url']) }}"
                                placeholder="https://graph.facebook.com/v20.0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Phone Number ID / Instance ID</label>
                            <input type="text" name="whatsapp_phone_number_id" class="form-control form-control-sm"
                                value="{{ old('whatsapp_phone_number_id', $gatewayConfig['phone_number_id']) }}"
                                placeholder="e.g. 105928374650192">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Business Account (WABA) ID</label>
                            <input type="text" name="whatsapp_business_account_id" class="form-control form-control-sm"
                                value="{{ old('whatsapp_business_account_id', $gatewayConfig['business_account_id']) }}"
                                placeholder="Optional WABA ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Sender WhatsApp Number</label>
                            <input type="text" name="whatsapp_sender_number" class="form-control form-control-sm"
                                value="{{ old('whatsapp_sender_number', $gatewayConfig['sender_number']) }}"
                                placeholder="e.g. 923115960959">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">API Access Token / Bearer Key</label>
                            <input type="password" name="whatsapp_access_token" class="form-control form-control-sm"
                                placeholder="{{ !empty($gatewayConfig['access_token']) ? '•••••••••••••••• (Configured — leave blank to keep)' : 'Enter Meta Permanent Access Token or Gateway API Key' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Webhook Verify Token</label>
                            <input type="text" name="whatsapp_verify_token" class="form-control form-control-sm"
                                value="{{ old('whatsapp_verify_token', $gatewayConfig['verify_token']) }}"
                                placeholder="Optional verify token">
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center pt-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="whatsapp_enabled" value="1" id="whatsapp_enabled"
                                    @checked($gatewayConfig['enabled'])>
                                <label class="form-check-label fw-bold small" for="whatsapp_enabled">
                                    Enable Automated WhatsApp Dispatch on Application Events
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save WhatsApp Config
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-paper-plane text-success me-2"></i> Quick Test WhatsApp Dispatcher
                </h6>
                <small class="text-muted">Test English or Urdu WhatsApp delivery to any mobile number</small>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.whatsapp-templates.send-test') }}" method="POST">
                    @csrf
                    <div class="row g-2">
                        <div class="col-sm-7">
                            <label class="form-label fw-bold small">Recipient WhatsApp No</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="0345-8161135 or 923458161135" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-bold small">Language</label>
                            <select name="language" id="test-wa-lang" class="form-select form-select-sm">
                                <option value="en">English</option>
                                <option value="ur">Urdu اردو</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Test WhatsApp Message</label>
                            <textarea name="message" id="test-wa-msg" rows="4" class="form-control form-control-sm" required>*ALF Gilgit-Baltistan Portal*
Dear Applicant, this is a test WhatsApp notification from Agriculture, Livestock & Fisheries Department Gilgit-Baltistan.</textarea>
                            <div class="form-text small">
                                Numbers are automatically normalized to `923XXXXXXXXX` international format.
                            </div>
                        </div>
                        <div class="col-12 pt-1">
                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">
                                <i class="fa-brands fa-whatsapp me-1"></i> Send Test WhatsApp Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Bulk WhatsApp Delivery Report (Shown Immediately After Bulk Dispatch) --}}
@if(session('bulk_whatsapp_report'))
    @php
        $bulkReport = session('bulk_whatsapp_report');
        $bulkSummary = $bulkReport['summary'] ?? ['total' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0];
        $bulkResults = $bulkReport['results'] ?? [];
    @endphp
    <div id="bulk-whatsapp-report" class="card alf-panel mb-4 shadow-sm border-success">
        <div class="card-header bg-success-subtle py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-clipboard-check text-success me-2"></i> Bulk WhatsApp Delivery Report — Messages Delivered to Each User
                </h5>
                <small class="text-muted">Per-recipient WhatsApp gateway status for your latest bulk dispatch</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-dark">Total: {{ $bulkSummary['total'] }}</span>
                <span class="badge bg-success">Delivered / Sent: {{ $bulkSummary['sent'] }}</span>
                @if(($bulkSummary['simulated'] ?? 0) > 0)
                    <span class="badge bg-info text-dark">Simulated: {{ $bulkSummary['simulated'] }}</span>
                @endif
                @if(($bulkSummary['failed'] ?? 0) > 0)
                    <span class="badge bg-danger">Failed: {{ $bulkSummary['failed'] }}</span>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="px-3 py-2 bg-light border-bottom small">
                <strong class="text-dark">Message Sent:</strong>
                <span class="text-dark" @if(($bulkReport['language'] ?? 'en') === 'ur') dir="rtl" lang="ur" @endif>{{ $bulkReport['message'] ?? '' }}</span>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light text-uppercase small text-muted sticky-top">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Recipient User</th>
                            <th>Mobile &amp; Operator</th>
                            <th>Delivery Status</th>
                            <th>WhatsApp Message ID &amp; Response</th>
                            <th>Message Delivered</th>
                            <th class="pe-3">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bulkResults as $idx => $item)
                            <tr>
                                <td class="ps-3 small text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold small text-dark">{{ $item['name'] }}</div>
                                    <div class="small text-muted" style="font-size: 11px;">
                                        <span class="badge {{ $item['source'] === 'Manual Entry' ? 'bg-warning-subtle text-warning-emphasis border' : 'bg-primary-subtle text-primary border' }}">
                                            {{ $item['source'] }}
                                        </span>
                                        @if(!empty($item['cnic']))
                                            <span class="ms-1">{{ $item['cnic'] }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="small fw-semibold text-dark">
                                    {{ $item['normalized_phone'] }}
                                    @if(!empty($item['operator']))
                                        <span class="badge bg-info-subtle text-info-emphasis border ms-1">{{ $item['operator'] }}</span>
                                    @endif
                                    @if(($item['raw_phone'] ?? '') !== $item['normalized_phone'])
                                        <div class="text-muted" style="font-size: 11px;">({{ $item['raw_phone'] }})</div>
                                    @endif
                                </td>
                                <td>
                                    @if($item['status'] === 'sent')
                                        <span class="badge bg-success"><i class="fa-solid fa-check-double me-1"></i>Delivered / Sent</span>
                                    @elseif($item['status'] === 'simulated')
                                        <span class="badge bg-info text-dark"><i class="fa-solid fa-flask me-1"></i>Simulated</span>
                                    @else
                                        <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if(!empty($item['provider_message_id']))
                                        <div><code>{{ $item['provider_message_id'] }}</code></div>
                                    @endif
                                    <div class="text-muted" style="font-size: 11px;" title="{{ $item['provider_response'] ?? '' }}">
                                        {{ $item['provider_description'] ?? '' }}
                                    </div>
                                </td>
                                <td class="small text-dark" style="max-width: 300px;">
                                    <div @if(($bulkReport['language'] ?? 'en') === 'ur') dir="rtl" lang="ur" @endif style="white-space: pre-wrap;">{{ $item['message'] }}</div>
                                </td>
                                <td class="pe-3 small text-muted text-nowrap">{{ $item['sent_at'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

{{-- Bulk WhatsApp Composer for Registered Applicants & Manual Numbers --}}
<div class="card alf-panel mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-brands fa-whatsapp text-success me-2"></i> Send Bulk WhatsApp Message to Registered Applicants &amp; Manual Numbers
            </h5>
            <small class="text-muted">Filter applicants by District, Tehsil, Scheme, or Mobile Operator (e.g. Zong, Jazz, Telenor, Ufone, SCOM), select recipients, and add manual numbers.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle" id="wa-bulk-selected-badge">
                0 applicant(s) selected
            </span>
            <span class="badge bg-secondary-subtle text-secondary border" id="wa-bulk-manual-badge">
                0 manual number(s)
            </span>
        </div>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.whatsapp-templates.send-bulk') }}" method="POST" id="wa-bulk-form">
            @csrf
            <div class="row g-4">
                {{-- Left Column: Message Composer & Manual Numbers --}}
                <div class="col-lg-5">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small mb-0">WhatsApp Message Text <span class="text-danger">*</span></label>
                            <small class="text-muted" id="wa-bulk-char-counter">0 chars</small>
                        </div>
                        <textarea name="message" id="wa-bulk-message" rows="5" class="form-control form-control-sm"
                            placeholder="Write your WhatsApp message for applicants (supports *bold*, _italic_, English & Urdu)..." required>{{ old('message') }}</textarea>
                        <div class="form-text small">
                            Supports WhatsApp formatting (<code>*bold*</code>, <code>_italic_</code>) in English and Urdu.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Language</label>
                        <select name="language" id="wa-bulk-language" class="form-select form-select-sm">
                            <option value="auto" @selected(old('language', 'auto') === 'auto')>Auto-detect (English / Urdu)</option>
                            <option value="en" @selected(old('language') === 'en')>English</option>
                            <option value="ur" @selected(old('language') === 'ur')>Urdu اردو</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Additional Manual Phone Numbers (Optional)</label>
                        <textarea name="manual_numbers" id="wa-bulk-manual-numbers" rows="3" class="form-control form-control-sm"
                            placeholder="Enter extra mobile numbers separated by comma or new line&#10;e.g. 0312-1234567, 923458161135">{{ old('manual_numbers') }}</textarea>
                        <div class="form-text small">
                            Numbers are automatically normalized to <code>923XXXXXXXXX</code> and deduplicated.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold" id="wa-bulk-submit-btn">
                            <i class="fa-brands fa-whatsapp me-1"></i> Send Bulk WhatsApp Now
                        </button>
                    </div>
                </div>

                {{-- Right Column: Registered Applicants Selection List with District, Tehsil, Scheme & Operator Filters --}}
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <label class="form-label fw-bold small mb-0">
                            Registered Applicants ({{ $applicants->count() }} with mobile numbers)
                        </label>
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-xs btn-outline-success btn-sm py-0 px-2" id="wa-btn-select-all">
                                <i class="fa-solid fa-check-double me-1"></i> Select All
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" id="wa-btn-select-visible">
                                Select Filtered
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2" id="wa-btn-clear">
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-sm-3">
                            <select name="filter_district_id" id="wa-district-filter" class="form-select form-select-sm">
                                <option value="">All Districts</option>
                                @foreach($districts as $dist)
                                    <option value="{{ $dist->id }}" @selected(old('filter_district_id') == $dist->id)>
                                        {{ $dist->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select name="filter_tehsil" id="wa-tehsil-filter" class="form-select form-select-sm">
                                <option value="">All Tehsils</option>
                                @foreach($tehsils ?? [] as $teh)
                                    <option value="{{ $teh }}" @selected(strtolower((string) old('filter_tehsil')) === strtolower($teh))>
                                        {{ $teh }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select name="filter_scheme_id" id="wa-scheme-filter" class="form-select form-select-sm">
                                <option value="">All Schemes</option>
                                @foreach($schemes ?? [] as $sch)
                                    <option value="{{ $sch->id }}" @selected(old('filter_scheme_id') == $sch->id)>
                                        {{ \Illuminate\Support\Str::limit($sch->title, 26) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select name="filter_operator" id="wa-operator-filter" class="form-select form-select-sm">
                                <option value="">All Operators</option>
                                @foreach($operators ?? [] as $opKey => $opLabel)
                                    <option value="{{ $opKey }}" @selected(strtolower((string) old('filter_operator')) === strtolower($opKey))>
                                        {{ $opLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <input type="text" id="wa-search-input" class="form-control form-control-sm"
                                placeholder="Search applicant name, mobile, CNIC, tehsil, scheme...">
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="select_all_applicants" value="1" id="wa_select_all_applicants"
                            @checked(old('select_all_applicants'))>
                        <label class="form-check-label small fw-semibold text-success" for="wa_select_all_applicants">
                            Select All Registered Applicants ({{ $applicants->count() }})
                        </label>
                    </div>

                    <div class="border rounded" style="max-height: 310px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" id="wa-applicants-table">
                            <thead class="table-light small text-muted sticky-top">
                                <tr>
                                    <th class="ps-2" style="width: 36px;"></th>
                                    <th>Applicant</th>
                                    <th>Mobile &amp; Operator</th>
                                    <th>District / Tehsil</th>
                                    <th>Scheme</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applicants as $applicant)
                                    @php
                                        $latestApp = $applicant->applications->first();
                                        $schemeTitle = $latestApp?->scheme?->title ?? 'Registered';
                                        $schemeIds = $applicant->applications->pluck('scheme_id')->filter()->unique()->implode(',');
                                        $distName = $applicant->district?->name ?? '—';
                                        $tehsilName = $applicant->tehsil ?: '—';
                                        $operatorName = \App\Services\WhatsappService::detectOperator($applicant->phone);
                                        $searchBlob = strtolower(implode(' ', [
                                            $applicant->full_name,
                                            $applicant->phone,
                                            $applicant->cnic,
                                            $distName,
                                            $tehsilName,
                                            $operatorName,
                                            $schemeTitle,
                                        ]));
                                    @endphp
                                    <tr class="wa-applicant-row"
                                        data-search="{{ $searchBlob }}"
                                        data-district-id="{{ (string) $applicant->district_id }}"
                                        data-tehsil="{{ strtolower(trim((string) $applicant->tehsil)) }}"
                                        data-scheme-ids=",{{ $schemeIds }},"
                                        data-operator="{{ strtolower($operatorName) }}">
                                        <td class="ps-2">
                                            <input class="form-check-input wa-applicant-checkbox"
                                                type="checkbox"
                                                name="applicant_ids[]"
                                                value="{{ $applicant->id }}"
                                                @checked(in_array($applicant->id, old('applicant_ids', [])))>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small text-dark">{{ $applicant->full_name }}</div>
                                            <div class="text-muted" style="font-size: 11px;">{{ $applicant->cnic }}</div>
                                        </td>
                                        <td class="small text-dark text-nowrap">
                                            <div>{{ $applicant->phone }}</div>
                                            <span class="badge bg-info-subtle text-info-emphasis border" style="font-size: 10px;">{{ $operatorName }}</span>
                                        </td>
                                        <td class="small text-muted">
                                            <div>{{ $distName }}</div>
                                            <div style="font-size: 11px;">{{ $tehsilName }}</div>
                                        </td>
                                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($schemeTitle, 26) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted small">
                                            No registered applicants with phone numbers found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Bilingual WhatsApp Templates Table --}}
<div class="card alf-panel mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-language text-success me-2"></i> Configured WhatsApp Templates (English &amp; Urdu)
            </h5>
            <small class="text-muted">Templates automatically trigger on registration, approval, rejection, GBPay payment success, or any configured application status.</small>
        </div>
        <a href="{{ route('admin.whatsapp-templates.create') }}" class="btn btn-sm btn-success">
            <i class="fa-solid fa-plus me-1"></i> Add WhatsApp Template
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-3" style="width: 22%;">Template &amp; Status Trigger</th>
                        <th style="width: 12%;">Scope &amp; Lang</th>
                        <th style="width: 28%;">English Content</th>
                        <th style="width: 28%;">Urdu Content (اردو)</th>
                        <th class="text-center" style="width: 5%;">Active</th>
                        <th class="text-end pe-3" style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $tpl->name }}</div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle mt-1">
                                    <i class="fa-solid fa-bolt me-1"></i> {{ $tpl->event_key }}
                                </span>
                                <div class="small text-muted mt-1" style="font-size: 11px;">
                                    {{ $tpl->eventLabel() }}
                                </div>
                            </td>
                            <td>
                                <div class="mb-1">
                                    @if($tpl->scheme)
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                            {{ \Illuminate\Support\Str::limit($tpl->scheme->title, 22) }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border">All Schemes</span>
                                    @endif
                                </div>
                                <div>
                                    @if($tpl->send_language === 'both')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">English + Urdu</span>
                                    @elseif($tpl->send_language === 'ur')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border">Urdu Only</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border">English Only</span>
                                    @endif
                                </div>
                                <div class="small text-muted mt-1" style="font-size: 11px;">
                                    Sent: <strong>{{ $tpl->logs_count }}</strong> times
                                </div>
                            </td>
                            <td>
                                @if($tpl->message_en)
                                    <div class="small p-2 rounded bg-light border text-dark" style="font-size: 12px; line-height: 1.4; white-space: pre-wrap;">{{ $tpl->message_en }}</div>
                                @else
                                    <span class="text-muted small fst-italic">Not configured</span>
                                @endif
                            </td>
                            <td>
                                @if($tpl->message_ur)
                                    <div class="small p-2 rounded bg-light border text-dark" dir="rtl" lang="ur" style="font-size: 13px; line-height: 1.6; white-space: pre-wrap; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, sans-serif;">{{ $tpl->message_ur }}</div>
                                @else
                                    <span class="text-muted small fst-italic">Not configured</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <form action="{{ route('admin.whatsapp-templates.toggle', $tpl) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $tpl->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="Click to toggle active status">
                                        @if($tpl->is_active)
                                            <i class="fa-solid fa-check"></i> On
                                        @else
                                            <i class="fa-solid fa-power-off"></i> Off
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('admin.whatsapp-templates.edit', $tpl) }}" class="btn btn-sm btn-outline-primary" title="Edit WhatsApp Template">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form action="{{ route('admin.whatsapp-templates.destroy', $tpl) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this WhatsApp template?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Template">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                No WhatsApp templates configured. Click "Create WhatsApp Template" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- WhatsApp Delivery Audit Logs --}}
<div class="card alf-panel shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> WhatsApp Delivery &amp; Gateway Logs
        </h5>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width: 190px;" placeholder="Search phone, name, msg ID...">
            <select name="operator" class="form-select form-select-sm" style="width: 140px;">
                <option value="">All Operators</option>
                @foreach($operators ?? [] as $opKey => $opLabel)
                    <option value="{{ $opKey }}" @selected(request('operator') === $opKey)>{{ $opKey }}</option>
                @endforeach
            </select>
            <select name="event_key" class="form-select form-select-sm" style="width: 170px;">
                <option value="">All Status Triggers</option>
                @foreach($eventLabels as $key => $label)
                    <option value="{{ $key }}" @selected(request('event_key') === $key)>{{ $key }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width: 130px;">
                <option value="">All Statuses</option>
                <option value="sent" @selected(request('status') === 'sent')>Sent (Live)</option>
                <option value="simulated" @selected(request('status') === 'simulated')>Simulated</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed</option>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-3">Date &amp; Time</th>
                        <th>Recipient &amp; Operator</th>
                        <th>Application</th>
                        <th>Trigger / Lang</th>
                        <th>Message Dispatched</th>
                        <th>Gateway Status &amp; Message ID</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="ps-3 small text-muted text-nowrap">
                                {{ optional($log->sent_at ?? $log->created_at)->format('d M Y, h:i A') }}
                            </td>
                            <td>
                                <div class="fw-bold small text-dark">
                                    {{ $log->recipient_phone }}
                                    @if($log->operator)
                                        <span class="badge bg-info-subtle text-info-emphasis border ms-1" style="font-size: 10px;">{{ $log->operator }}</span>
                                    @endif
                                </div>
                                @if($log->recipient_name)
                                    <div class="text-muted" style="font-size: 11px;">{{ $log->recipient_name }}</div>
                                @endif
                            </td>
                            <td>
                                @if($log->application)
                                    <a href="{{ route('admin.applications.show', $log->application) }}" class="badge bg-light text-primary border text-decoration-none">
                                        {{ $log->application->application_no }}
                                    </a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $log->event_key }}</span>
                                <span class="badge {{ $log->language === 'ur' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-info-subtle text-info-emphasis' }}">
                                    {{ strtoupper($log->language) }}
                                </span>
                            </td>
                            <td style="max-width: 380px;">
                                <div class="small text-dark" @if($log->language === 'ur') dir="rtl" lang="ur" @endif style="white-space: pre-wrap;">{{ $log->message }}</div>
                            </td>
                            <td>
                                @if($log->status === 'sent')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Sent</span>
                                @elseif($log->status === 'simulated')
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-flask me-1"></i>Simulated</span>
                                @else
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                                @endif
                                @if($log->provider_message_id)
                                    <div class="small text-muted" style="font-size: 11px;">ID: <code>{{ $log->provider_message_id }}</code></div>
                                @endif
                                <div class="small text-muted" style="font-size: 10px;" title="{{ $log->provider_response }}">
                                    {{ \App\Models\WhatsappLog::statusDescription($log->provider_status_code) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                No WhatsApp logs recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white border-top py-2">
            {{ $logs->links() }}
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const masterCheckbox = document.getElementById('wa_select_all_applicants');
    const rowCheckboxes = Array.from(document.querySelectorAll('.wa-applicant-checkbox'));
    const applicantRows = Array.from(document.querySelectorAll('.wa-applicant-row'));
    const searchInput = document.getElementById('wa-search-input');
    const districtFilter = document.getElementById('wa-district-filter');
    const tehsilFilter = document.getElementById('wa-tehsil-filter');
    const schemeFilter = document.getElementById('wa-scheme-filter');
    const operatorFilter = document.getElementById('wa-operator-filter');
    const btnSelectAll = document.getElementById('wa-btn-select-all');
    const btnSelectVisible = document.getElementById('wa-btn-select-visible');
    const btnClear = document.getElementById('wa-btn-clear');
    const selectedBadge = document.getElementById('wa-bulk-selected-badge');
    const manualBadge = document.getElementById('wa-bulk-manual-badge');
    const manualInput = document.getElementById('wa-bulk-manual-numbers');
    const msgInput = document.getElementById('wa-bulk-message');
    const charCounter = document.getElementById('wa-bulk-char-counter');

    function updateCounts() {
        const checkedCount = rowCheckboxes.filter(cb => cb.checked).length;
        if (selectedBadge) {
            selectedBadge.textContent = checkedCount + ' applicant(s) selected';
        }
        if (masterCheckbox && rowCheckboxes.length > 0) {
            masterCheckbox.checked = checkedCount === rowCheckboxes.length;
        }
        if (manualBadge && manualInput) {
            const tokens = manualInput.value.trim().split(/[\s,;]+/).filter(Boolean);
            manualBadge.textContent = tokens.length + ' manual number(s)';
        }
    }

    function updateCharCounter() {
        if (!msgInput || !charCounter) return;
        const len = msgInput.value.length;
        charCounter.textContent = len + ' / 4096 chars';
    }

    function filterRows() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const distId = (districtFilter ? districtFilter.value : '').trim();
        const tehsil = (tehsilFilter ? tehsilFilter.value : '').trim().toLowerCase();
        const schemeId = (schemeFilter ? schemeFilter.value : '').trim();
        const operator = (operatorFilter ? operatorFilter.value : '').trim().toLowerCase();

        applicantRows.forEach(row => {
            const hay = row.dataset.search || '';
            const rowDistId = row.dataset.districtId || '';
            const rowTehsil = row.dataset.tehsil || '';
            const rowSchemeIds = row.dataset.schemeIds || '';
            const rowOp = row.dataset.operator || '';

            const matchesQ = !q || hay.includes(q);
            const matchesDist = !distId || rowDistId === distId;
            const matchesTehsil = !tehsil || rowTehsil === tehsil;
            const matchesScheme = !schemeId || rowSchemeIds.includes(',' + schemeId + ',');
            const matchesOp = !operator || rowOp === operator;

            row.style.display = (matchesQ && matchesDist && matchesTehsil && matchesScheme && matchesOp) ? '' : 'none';
        });
    }

    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function () {
            applicantRows.forEach(row => {
                const cb = row.querySelector('.wa-applicant-checkbox');
                if (!cb) return;
                if (row.style.display !== 'none') {
                    cb.checked = masterCheckbox.checked;
                } else if (!masterCheckbox.checked) {
                    cb.checked = false;
                }
            });
            updateCounts();
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            if (masterCheckbox) masterCheckbox.checked = true;
            applicantRows.forEach(row => {
                const cb = row.querySelector('.wa-applicant-checkbox');
                if (cb && row.style.display !== 'none') {
                    cb.checked = true;
                }
            });
            updateCounts();
        });
    }

    if (btnSelectVisible) {
        btnSelectVisible.addEventListener('click', function () {
            applicantRows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cb = row.querySelector('.wa-applicant-checkbox');
                    if (cb) cb.checked = true;
                }
            });
            updateCounts();
        });
    }

    if (btnClear) {
        btnClear.addEventListener('click', function () {
            if (masterCheckbox) masterCheckbox.checked = false;
            rowCheckboxes.forEach(cb => { cb.checked = false; });
            updateCounts();
        });
    }

    rowCheckboxes.forEach(cb => cb.addEventListener('change', updateCounts));
    if (searchInput) searchInput.addEventListener('input', filterRows);
    if (districtFilter) districtFilter.addEventListener('change', filterRows);
    if (tehsilFilter) tehsilFilter.addEventListener('change', filterRows);
    if (schemeFilter) schemeFilter.addEventListener('change', filterRows);
    if (operatorFilter) operatorFilter.addEventListener('change', filterRows);
    if (manualInput) manualInput.addEventListener('input', updateCounts);
    if (msgInput) msgInput.addEventListener('input', updateCharCounter);

    filterRows();
    updateCounts();
    updateCharCounter();
});
</script>
@endsection


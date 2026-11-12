@extends('layouts.admin')

@section('title', 'SMS Templates & Zong CBS Gateway')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'SMS Notifications & Zong 4G CBS Gateway',
    'subtitle' => 'Configure bilingual (English & Urdu) SMS templates for registration, approval, rejection, GBPay bill payment, and all application statuses.',
    'icon' => 'fa-solid fa-comment-sms',
    'actions' => [
        ['label' => 'Create SMS Template', 'url' => route('admin.sms-templates.create'), 'class' => 'btn btn-primary'],
    ],
])

@php
    $pageStats = [
        ['label' => 'Total Templates', 'value' => $stats['total_templates'], 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
        ['label' => 'Active Triggers', 'value' => $stats['active_templates'], 'icon' => 'fa-solid fa-bolt', 'tone' => 'green'],
        ['label' => 'Live SMS Sent', 'value' => $stats['total_sent'], 'icon' => 'fa-solid fa-paper-plane', 'tone' => 'teal'],
        ['label' => 'Simulated / Failed', 'value' => $stats['total_simulated'] . ' / ' . $stats['total_failed'], 'icon' => 'fa-solid fa-clock-rotate-left', 'tone' => 'amber'],
    ];
@endphp

@include('admin.partials.stats', ['stats' => $pageStats])

{{-- Zong 4G CBS Gateway Configuration & Test SMS Row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h6 class="mb-0 fw-bold text-dark">
                        <i class="fa-solid fa-tower-cell text-success me-2"></i> Zong 4G Corporate Bulk SMS (CBS) API Configuration
                    </h6>
                    <small class="text-muted">REST &amp; SOAP API v2.0 (`https://cbs.zong.com.pk/reachrestapi/home`)</small>
                </div>
                <form action="{{ route('admin.sms-templates.check-balance') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        <i class="fa-solid fa-wallet me-1"></i> Check Live Balance
                    </button>
                </form>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.sms-templates.gateway.update') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Zong CBS Account `loginId`</label>
                            <input type="text" name="zong_cbs_login_id" class="form-control form-control-sm"
                                value="{{ old('zong_cbs_login_id', $gatewayConfig['login_id']) }}" placeholder="e.g. 923115960959">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Zong CBS `loginPassword`</label>
                            <input type="password" name="zong_cbs_login_password" class="form-control form-control-sm"
                                placeholder="{{ !empty($gatewayConfig['login_password']) ? '•••••••••••• (Configured — leave blank to keep)' : 'Enter CBS account password' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Brand Mask (`Mask`)</label>
                            <input type="text" name="zong_cbs_mask" class="form-control form-control-sm"
                                value="{{ old('zong_cbs_mask', $gatewayConfig['mask']) }}" placeholder="e.g. ALF-GB or Zong CBS TR" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small">API Protocol Mode</label>
                            <select name="zong_cbs_api_mode" class="form-select form-select-sm">
                                <option value="rest" @selected($gatewayConfig['api_mode'] === 'rest')>REST API (`/SendQuickSMS`)</option>
                                <option value="rest_encoded" @selected($gatewayConfig['api_mode'] === 'rest_encoded')>REST Encoded (`/SendQuickSMSEncoded` UTF-16LE)</option>
                                <option value="soap" @selected($gatewayConfig['api_mode'] === 'soap')>SOAP API (`/QuickSMS` XML)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small">ShortCode (`N`/`Y`)</label>
                            <select name="zong_cbs_shortcode_preferred" class="form-select form-select-sm">
                                <option value="N" @selected(strtoupper($gatewayConfig['shortcode_preferred']) === 'N')>N (Masking)</option>
                                <option value="Y" @selected(strtoupper($gatewayConfig['shortcode_preferred']) === 'Y')>Y (ShortCode)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small">REST Base URL</label>
                            <input type="url" name="zong_cbs_base_url" class="form-control form-control-sm"
                                value="{{ old('zong_cbs_base_url', $gatewayConfig['base_url']) }}" required>
                            <input type="hidden" name="zong_cbs_soap_url" value="{{ $gatewayConfig['soap_url'] }}">
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center pt-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sms_enabled" value="1" id="sms_enabled"
                                    @checked($gatewayConfig['enabled'])>
                                <label class="form-check-label fw-bold small" for="sms_enabled">
                                    Enable Automated SMS Dispatch on Application Events
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Gateway Config
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
                    <i class="fa-solid fa-paper-plane text-primary me-2"></i> Quick Test SMS Dispatcher
                </h6>
                <small class="text-muted">Test English (`UniCode=0`) or Urdu (`UniCode=1`) SMS delivery</small>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.sms-templates.send-test') }}" method="POST">
                    @csrf
                    <div class="row g-2">
                        <div class="col-sm-7">
                            <label class="form-label fw-bold small">Recipient Mobile No</label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="0345-8161135 or 923458161135" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-bold small">Character Set / Language</label>
                            <select name="language" id="test-sms-lang" class="form-select form-select-sm">
                                <option value="en">English (UniCode=0)</option>
                                <option value="ur">Urdu اردو (UniCode=1)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Test Message</label>
                            <textarea name="message" id="test-sms-msg" rows="3" class="form-control form-control-sm" required>Dear Applicant, this is a test SMS from Agriculture, Livestock & Fisheries Department Gilgit-Baltistan Online Portal.</textarea>
                            <div class="form-text small">
                                Numbers are automatically normalized to `923XXXXXXXXX` format for Zong CBS.
                            </div>
                        </div>
                        <div class="col-12 pt-1">
                            <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">
                                <i class="fa-solid fa-paper-plane me-1"></i> Send Test SMS Now
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Bulk SMS Delivery Report (Shown Immediately After Bulk Dispatch) --}}
@if(session('bulk_sms_report'))
    @php
        $bulkReport = session('bulk_sms_report');
        $bulkSummary = $bulkReport['summary'] ?? ['total' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0];
        $bulkResults = $bulkReport['results'] ?? [];
    @endphp
    <div id="bulk-sms-report" class="card alf-panel mb-4 shadow-sm border-success">
        <div class="card-header bg-success-subtle py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-bottom">
            <div>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-clipboard-check text-success me-2"></i> Bulk SMS Delivery Report — Messages Delivered to Each User
                </h5>
                <small class="text-muted">Per-recipient gateway status for your latest bulk SMS dispatch</small>
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
                            <th>Mobile Number</th>
                            <th>Delivery Status</th>
                            <th>Zong SMS ID &amp; Response</th>
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
                                    @if(!empty($item['zong_sms_id']))
                                        <div><code>{{ $item['zong_sms_id'] }}</code></div>
                                    @endif
                                    <div class="text-muted" style="font-size: 11px;" title="{{ $item['zong_response'] ?? '' }}">
                                        {{ $item['zong_description'] ?? '' }}
                                    </div>
                                </td>
                                <td class="small text-dark" style="max-width: 300px;">
                                    <div @if(($bulkReport['language'] ?? 'en') === 'ur') dir="rtl" lang="ur" @endif>
                                        {{ $item['message'] }}
                                    </div>
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

{{-- Bulk SMS Composer for Registered Applicants & Manual Numbers --}}
<div class="card alf-panel mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-users-rays text-success me-2"></i> Send Bulk SMS to Registered Applicants &amp; Manual Numbers
            </h5>
            <small class="text-muted">Compose a custom SMS, select applicants from the registered list (or Select All), and optionally add manual mobile numbers.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="bulk-selected-badge">
                0 applicant(s) selected
            </span>
            <span class="badge bg-secondary-subtle text-secondary border" id="bulk-manual-badge">
                0 manual number(s)
            </span>
        </div>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.sms-templates.send-bulk') }}" method="POST" id="bulk-sms-form">
            @csrf
            <div class="row g-4">
                {{-- Left Column: Message Composer & Manual Numbers --}}
                <div class="col-lg-5">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold small mb-0">SMS Message Text <span class="text-danger">*</span></label>
                            <small class="text-muted" id="bulk-char-counter">0 chars (0 SMS)</small>
                        </div>
                        <textarea name="message" id="bulk-sms-message" rows="5" class="form-control form-control-sm"
                            placeholder="Write your announcement or notice for applicants (English or Urdu)..." required>{{ old('message') }}</textarea>
                        <div class="form-text small">
                            Supports both English (<code>UniCode=0</code>) and Urdu (<code>UniCode=1</code>).
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Character Encoding / Language</label>
                        <select name="language" id="bulk-sms-language" class="form-select form-select-sm">
                            <option value="auto" @selected(old('language', 'auto') === 'auto')>Auto-detect (English / Urdu Unicode)</option>
                            <option value="en" @selected(old('language') === 'en')>English (UniCode=0)</option>
                            <option value="ur" @selected(old('language') === 'ur')>Urdu اردو (UniCode=1)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Additional Manual Phone Numbers (Optional)</label>
                        <textarea name="manual_numbers" id="bulk-manual-numbers" rows="3" class="form-control form-control-sm"
                            placeholder="Enter extra mobile numbers separated by comma or new line&#10;e.g. 0345-8161135, 923001234567">{{ old('manual_numbers') }}</textarea>
                        <div class="form-text small">
                            Numbers are automatically normalized to <code>923XXXXXXXXX</code> and deduplicated.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold" id="bulk-sms-submit-btn">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send Bulk SMS Now
                        </button>
                    </div>
                </div>

                {{-- Right Column: Registered Applicants Selection List --}}
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <label class="form-label fw-bold small mb-0">
                            Registered Applicants ({{ $applicants->count() }} with mobile numbers)
                        </label>
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" id="btn-select-all-applicants">
                                <i class="fa-solid fa-check-double me-1"></i> Select All
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" id="btn-select-visible-applicants">
                                Select Filtered
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-sm py-0 px-2" id="btn-clear-applicants">
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-sm-7">
                            <input type="text" id="applicant-search-input" class="form-control form-control-sm"
                                placeholder="Search applicant name, mobile, CNIC, scheme...">
                        </div>
                        <div class="col-sm-5">
                            <select id="applicant-district-filter" class="form-select form-select-sm">
                                <option value="">All Districts</option>
                                @foreach($districts as $dist)
                                    <option value="{{ strtolower($dist->name) }}">{{ $dist->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="select_all_applicants" value="1" id="select_all_applicants"
                            @checked(old('select_all_applicants'))>
                        <label class="form-check-label small fw-semibold text-primary" for="select_all_applicants">
                            Select All Registered Applicants ({{ $applicants->count() }})
                        </label>
                    </div>

                    <div class="border rounded" style="max-height: 310px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" id="bulk-applicants-table">
                            <thead class="table-light small text-muted sticky-top">
                                <tr>
                                    <th class="ps-2" style="width: 36px;"></th>
                                    <th>Applicant</th>
                                    <th>Mobile No</th>
                                    <th>District</th>
                                    <th>Latest Scheme</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applicants as $applicant)
                                    @php
                                        $latestApp = $applicant->applications->first();
                                        $schemeTitle = $latestApp?->scheme?->title ?? 'Registered';
                                        $distName = $applicant->district?->name ?? '—';
                                        $searchBlob = strtolower(implode(' ', [
                                            $applicant->full_name,
                                            $applicant->phone,
                                            $applicant->cnic,
                                            $distName,
                                            $schemeTitle,
                                        ]));
                                    @endphp
                                    <tr class="applicant-row"
                                        data-search="{{ $searchBlob }}"
                                        data-district="{{ strtolower($distName) }}">
                                        <td class="ps-2">
                                            <input class="form-check-input applicant-checkbox"
                                                type="checkbox"
                                                name="applicant_ids[]"
                                                value="{{ $applicant->id }}"
                                                @checked(in_array($applicant->id, old('applicant_ids', [])))>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small text-dark">{{ $applicant->full_name }}</div>
                                            <div class="text-muted" style="font-size: 11px;">{{ $applicant->cnic }}</div>
                                        </td>
                                        <td class="small text-dark text-nowrap">{{ $applicant->phone }}</td>
                                        <td class="small text-muted">{{ $distName }}</td>
                                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($schemeTitle, 28) }}</td>
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

{{-- Bilingual SMS Templates Table --}}
<div class="card alf-panel mb-4 shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-language text-primary me-2"></i> Configured SMS Templates (English &amp; Urdu)
            </h5>
            <small class="text-muted">Templates automatically trigger on registration, approval, rejection, GBPay payment success, or any configured application status.</small>
        </div>
        <a href="{{ route('admin.sms-templates.create') }}" class="btn btn-sm btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Add Status SMS Template
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small text-muted">
                    <tr>
                        <th class="ps-3" style="width: 22%;">Template &amp; Status Trigger</th>
                        <th style="width: 12%;">Scope &amp; Lang</th>
                        <th style="width: 28%;">English Content (`UniCode=0`)</th>
                        <th style="width: 28%;">Urdu Content (`UniCode=1`)</th>
                        <th class="text-center" style="width: 5%;">Active</th>
                        <th class="text-end pe-3" style="width: 5%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tpl)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $tpl->name }}</div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1">
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
                                    <div class="small p-2 rounded bg-light border text-dark" style="font-size: 12px; line-height: 1.4;">
                                        {{ $tpl->message_en }}
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic">Not configured</span>
                                @endif
                            </td>
                            <td>
                                @if($tpl->message_ur)
                                    <div class="small p-2 rounded bg-light border text-dark" dir="rtl" lang="ur" style="font-size: 13px; line-height: 1.6; font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', Tahoma, sans-serif;">
                                        {{ $tpl->message_ur }}
                                    </div>
                                @else
                                    <span class="text-muted small fst-italic">Not configured</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <form action="{{ route('admin.sms-templates.toggle', $tpl) }}" method="POST">
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
                                <a href="{{ route('admin.sms-templates.edit', $tpl) }}" class="btn btn-sm btn-outline-primary" title="Edit English & Urdu Template">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form action="{{ route('admin.sms-templates.destroy', $tpl) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this SMS template?');">
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
                                No SMS templates configured. Click "Create SMS Template" to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- SMS Delivery Audit Logs --}}
<div class="card alf-panel shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom flex-wrap gap-2">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> SMS Delivery &amp; Gateway Logs
        </h5>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="width: 200px;" placeholder="Search phone, name, SMS ID...">
            <select name="event_key" class="form-select form-select-sm" style="width: 180px;">
                <option value="">All Status Triggers</option>
                @foreach($eventLabels as $key => $label)
                    <option value="{{ $key }}" @selected(request('event_key') === $key)>{{ $key }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width: 140px;">
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
                        <th>Recipient</th>
                        <th>Application</th>
                        <th>Trigger / Lang</th>
                        <th>Message Dispatched</th>
                        <th>Gateway Status &amp; SMS ID</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="ps-3 small text-muted text-nowrap">
                                {{ optional($log->sent_at ?? $log->created_at)->format('d M Y, h:i A') }}
                            </td>
                            <td>
                                <div class="fw-bold small text-dark">{{ $log->recipient_phone }}</div>
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
                                    {{ strtoupper($log->language) }} ({{ $log->is_unicode ? 'UniCode=1' : 'UniCode=0' }})
                                </span>
                            </td>
                            <td style="max-width: 380px;">
                                <div class="small text-dark" @if($log->language === 'ur') dir="rtl" lang="ur" @endif>
                                    {{ $log->message }}
                                </div>
                            </td>
                            <td>
                                @if($log->status === 'sent')
                                    <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Sent</span>
                                @elseif($log->status === 'simulated')
                                    <span class="badge bg-info text-dark"><i class="fa-solid fa-flask me-1"></i>Simulated</span>
                                @else
                                    <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                                @endif
                                @if($log->zong_sms_id)
                                    <div class="small text-muted" style="font-size: 11px;">ID: <code>{{ $log->zong_sms_id }}</code></div>
                                @endif
                                <div class="small text-muted" style="font-size: 10px;" title="{{ $log->zong_response }}">
                                    {{ \App\Models\SmsLog::zongCodeDescription($log->zong_status_code) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                No SMS logs recorded yet.
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
    const masterCheckbox = document.getElementById('select_all_applicants');
    const rowCheckboxes = Array.from(document.querySelectorAll('.applicant-checkbox'));
    const applicantRows = Array.from(document.querySelectorAll('.applicant-row'));
    const searchInput = document.getElementById('applicant-search-input');
    const districtFilter = document.getElementById('applicant-district-filter');
    const btnSelectAll = document.getElementById('btn-select-all-applicants');
    const btnSelectVisible = document.getElementById('btn-select-visible-applicants');
    const btnClear = document.getElementById('btn-clear-applicants');
    const selectedBadge = document.getElementById('bulk-selected-badge');
    const manualBadge = document.getElementById('bulk-manual-badge');
    const manualInput = document.getElementById('bulk-manual-numbers');
    const msgInput = document.getElementById('bulk-sms-message');
    const langSelect = document.getElementById('bulk-sms-language');
    const charCounter = document.getElementById('bulk-char-counter');

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
        const text = msgInput.value;
        const len = text.length;
        const isUnicode = (langSelect && langSelect.value === 'ur') || /[^\x20-\x7E\r\n]/.test(text);
        const perPart = isUnicode ? 70 : 160;
        const parts = len === 0 ? 0 : Math.ceil(len / perPart);
        charCounter.textContent = len + ' chars (' + parts + ' SMS, ' + (isUnicode ? 'Unicode' : 'GSM-7') + ')';
    }

    function filterRows() {
        const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const dist = (districtFilter ? districtFilter.value : '').trim().toLowerCase();
        applicantRows.forEach(row => {
            const hay = row.dataset.search || '';
            const rowDist = row.dataset.district || '';
            const matchesQ = !q || hay.includes(q);
            const matchesDist = !dist || rowDist === dist;
            row.style.display = (matchesQ && matchesDist) ? '' : 'none';
        });
    }

    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => { cb.checked = masterCheckbox.checked; });
            updateCounts();
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            if (masterCheckbox) masterCheckbox.checked = true;
            rowCheckboxes.forEach(cb => { cb.checked = true; });
            updateCounts();
        });
    }

    if (btnSelectVisible) {
        btnSelectVisible.addEventListener('click', function () {
            applicantRows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cb = row.querySelector('.applicant-checkbox');
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
    if (manualInput) manualInput.addEventListener('input', updateCounts);
    if (msgInput) msgInput.addEventListener('input', updateCharCounter);
    if (langSelect) langSelect.addEventListener('change', updateCharCounter);

    updateCounts();
    updateCharCounter();
});
</script>
@endsection


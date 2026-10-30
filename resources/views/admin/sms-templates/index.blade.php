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
@endsection


@extends('layouts.admin')

@section('title', 'GBPay API Sandbox & Testing Console')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'GBPay API Sandbox & Testing Console',
    'subtitle' => 'Interactively test Bill Inquiry, Bill Payment, Search PSID, and Webhook endpoints with live JSON request/response inspection.',
    'icon' => 'fa-solid fa-flask-vial',
    'actions' => [
        ['label' => 'GBPay & PSID Configuration', 'url' => route('admin.gbpay.index'), 'class' => 'btn btn-outline-secondary'],
        ['label' => 'Applications', 'url' => route('admin.applications.index'), 'class' => 'btn btn-primary'],
    ],
])

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $defaultCredUsername = $credentials->first()?->username ?? 'alfgb@gbpay.gov.pk';
    $requestedPsid = request('psid');
    $initialBill = $requestedPsid
        ? ($sampleBills->firstWhere('psid', $requestedPsid) ?? $sampleBills->first())
        : ($sampleBills->firstWhere('status', 'pending') ?? $sampleBills->first());
    $activePrefix = ($psidConfig->one_link_prefix ?? '101530')
        . str_pad((string) ($psidConfig->department_code ?? '09'), 2, '0', STR_PAD_LEFT)
        . str_pad((string) ($psidConfig->department_service_no ?? '01'), 2, '0', STR_PAD_LEFT);
@endphp

{{-- Top Bar: Sample Bill Selector + Generate Test Bill + Auth Mode --}}
<div class="card alf-panel shadow-sm mb-4 border-top border-3 border-primary">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            {{-- 1. Select Existing Bill / PSID --}}
            <div class="col-lg-5">
                <label class="form-label small fw-bold text-dark mb-1">
                    <i class="fa-solid fa-barcode text-primary me-1"></i> 1. Select Sample Bill / 20-Digit PSID to Auto-Fill
                </label>
                <select id="sampleBillSelector" class="form-select form-select-sm">
                    @forelse($sampleBills as $b)
                        @php
                            $app = $b->application;
                            $applicant = $app?->applicant;
                        @endphp
                        <option value="{{ $b->psid }}"
                            data-bill-id="{{ $b->bill_id }}"
                            data-psid="{{ $b->psid }}"
                            data-amount="{{ (int) ceil((float) $b->bill_amount) }}"
                            data-status="{{ $b->status }}"
                            data-txn="{{ $b->transaction_id ?? '' }}"
                            data-app-no="{{ $app?->application_no ?? '' }}"
                            data-challan-no="{{ $app?->challan_no ?? '' }}"
                            data-name="{{ $applicant?->full_name ?? 'Applicant' }}"
                            data-cnic="{{ $applicant?->cnic ?? '' }}"
                            data-phone="{{ $applicant?->phone ?? '' }}"
                            data-district="{{ $app?->district?->name ?? 'Gilgit' }}"
                            data-reset-url="{{ route('admin.gbpay.test.reset-bill', $b->bill_id) }}"
                            @selected($initialBill && $initialBill->bill_id === $b->bill_id)>
                            [{{ strtoupper($b->status) }}] {{ $b->psid }} — Rs. {{ number_format((int) ceil((float) $b->bill_amount)) }} — {{ $applicant?->full_name ?? 'Applicant' }} ({{ $app?->application_no ?? 'App #' . $b->application_id }})
                        </option>
                    @empty
                        <option value="">No bills generated yet — click "Generate Sample Bill" on the right</option>
                    @endforelse
                </select>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="text-muted" style="font-size: 11px;">
                        Active PSID Prefix: <code>{{ $activePrefix }}</code> (10 digits + 10-digit voucher)
                    </span>
                    <form id="resetBillForm" method="POST" action="{{ $initialBill ? route('admin.gbpay.test.reset-bill', $initialBill->bill_id) : '#' }}" class="{{ $initialBill ? '' : 'd-none' }}">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" style="font-size: 11px;" title="Reset selected bill status back to PENDING for re-testing payment">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset Selected Bill to PENDING
                        </button>
                    </form>
                </div>
            </div>

            {{-- 2. Authentication Mode --}}
            <div class="col-lg-3">
                <label class="form-label small fw-bold text-dark mb-1">
                    <i class="fa-solid fa-shield-halved text-success me-1"></i> 2. API Authentication Mode
                </label>
                <select id="globalAuthMode" class="form-select form-select-sm">
                    <option value="admin_bypass" selected>Admin Sandbox Mode (Auto-Auth)</option>
                    <option value="credentials">Verify Username & Password (Strict)</option>
                </select>
                <div class="form-text" style="font-size: 11px;">
                    Switch to <em>Strict</em> to test <code>04</code> password validation.
                </div>
            </div>

            {{-- 3. Generate New Sample Test Bill --}}
            <div class="col-lg-4">
                <form action="{{ route('admin.gbpay.test.sample-bill') }}" method="POST" class="p-2 rounded bg-light border">
                    @csrf
                    <div class="small fw-bold text-dark mb-1">
                        <i class="fa-solid fa-wand-magic-sparkles text-warning me-1"></i> Generate / Refresh Test PSID Bill
                    </div>
                    <div class="input-group input-group-sm">
                        <select name="application_id" class="form-select">
                            @forelse($sampleApplications as $sApp)
                                <option value="{{ $sApp->id }}">
                                    {{ $sApp->application_no }} — {{ $sApp->applicant?->full_name ?? 'Applicant' }}
                                </option>
                            @empty
                                <option value="">No applications available</option>
                            @endforelse
                        </select>
                        <input type="number" name="bill_amount" class="form-control" style="max-width: 95px;" placeholder="Rs. Auto" min="1" step="1" title="Optional integer bill amount override">
                        <input type="hidden" name="force_new" value="1">
                        <button type="submit" class="btn btn-primary fw-bold" @disabled($sampleApplications->isEmpty())>
                            <i class="fa-solid fa-plus me-1"></i> New PSID
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Main Split View: Left = API Request Builder, Right = Live Response Inspector --}}
<div class="row g-3 mb-4">
    {{-- LEFT COLUMN: API Endpoint Tabs & Request Form --}}
    <div class="col-lg-6">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white p-2 border-bottom">
                <ul class="nav nav-pills nav-fill gap-1 small fw-bold" id="gbpayTestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-2" id="tab-inquiry-btn" data-bs-toggle="pill" data-bs-target="#pane-inquiry" type="button" role="tab" data-endpoint="bill_inquiry">
                            <i class="fa-solid fa-magnifying-glass-dollar me-1"></i> 1. Bill Inquiry
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-2" id="tab-payment-btn" data-bs-toggle="pill" data-bs-target="#pane-payment" type="button" role="tab" data-endpoint="bill_payment">
                            <i class="fa-solid fa-money-bill-transfer me-1"></i> 2. Bill Payment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-2" id="tab-search-btn" data-bs-toggle="pill" data-bs-target="#pane-search" type="button" role="tab" data-endpoint="search_psid">
                            <i class="fa-solid fa-id-card me-1"></i> 3. Search PSID
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-2" id="tab-webhook-btn" data-bs-toggle="pill" data-bs-target="#pane-webhook" type="button" role="tab" data-endpoint="webhook">
                            <i class="fa-solid fa-satellite-dish me-1"></i> 4. Webhook
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                {{-- Shared API Credentials Row (Used by Inquiry, Payment, Search PSID) --}}
                <div id="sharedCredentialsBox" class="p-2 rounded bg-light border mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-dark"><i class="fa-solid fa-key text-warning me-1"></i> Request Payload Credentials</span>
                        <span class="badge bg-secondary" style="font-size: 10px;">Default Seeded Password: ChangeThisPassword123!</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label mb-1" style="font-size: 11px;">Username</label>
                            <input type="text" id="apiUsername" class="form-control form-control-sm" value="{{ $defaultCredUsername }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1" style="font-size: 11px;">Password</label>
                            <input type="text" id="apiPassword" class="form-control form-control-sm" value="ChangeThisPassword123!">
                        </div>
                    </div>
                </div>

                <div class="tab-content">
                    {{-- TAB 1: BILL INQUIRY --}}
                    <div class="tab-pane fade show active" id="pane-inquiry" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-primary me-1">POST</span>
                                <code class="small">{{ url('/api/gb-pay/bill-inquiry') }}</code>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-inquiry" data-preset="valid" style="font-size: 11px;">Valid (00)</button>
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-inquiry" data-preset="not_found" style="font-size: 11px;">Not Found (01)</button>
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-inquiry" data-preset="bad_auth" style="font-size: 11px;">Bad Auth (04)</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Voucher Number / 20-Digit PSID (<code>voucher_number</code>) <span class="text-danger">*</span></label>
                            <input type="text" id="inqVoucherNumber" class="form-control form-control-sm font-monospace"
                                value="{{ $initialBill?->psid ?? ($activePrefix . '0000000001') }}" placeholder="20-digit PSID or 10-digit voucher">
                            <div class="form-text" style="font-size: 11px;">
                                Accepts full 20-digit PSID (e.g. <code>{{ $activePrefix }}0000000001</code>), 14-digit consumer suffix, or 10-digit voucher number.
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary w-100 fw-bold send-api-btn" data-endpoint="bill_inquiry">
                            <i class="fa-solid fa-paper-plane me-1"></i> Test Bill Inquiry API
                        </button>
                    </div>

                    {{-- TAB 2: BILL PAYMENT --}}
                    <div class="tab-pane fade" id="pane-payment" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-success me-1">POST</span>
                                <code class="small">{{ url('/api/gb-pay/bill-payment') }}</code>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-payment" data-preset="valid" style="font-size: 11px;">Valid Pay (00)</button>
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-payment" data-preset="mismatch" style="font-size: 11px;">Mismatch (05)</button>
                                <button type="button" class="btn btn-outline-secondary py-0 px-2 preset-payment" data-preset="not_found" style="font-size: 11px;">Not Found (01)</button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold mb-1">Voucher Number / 20-Digit PSID (<code>voucher_number</code>) <span class="text-danger">*</span></label>
                                <input type="text" id="payVoucherNumber" class="form-control form-control-sm font-monospace"
                                    value="{{ $initialBill?->psid ?? ($activePrefix . '0000000001') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-1">Transaction ID (<code>transaction_id</code>) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="payTransactionId" class="form-control font-monospace"
                                        value="1LINK-{{ now()->format('Ymd') }}-{{ rand(100000, 999999) }}">
                                    <button type="button" class="btn btn-outline-secondary" id="btnRandomTxn" title="Generate new Transaction ID">
                                        <i class="fa-solid fa-dice"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Amount (Int) <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" id="payTransactionAmount" class="form-control form-control-sm fw-bold"
                                    value="{{ $initialBill ? (int) ceil((float) $initialBill->bill_amount) : 3000 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Bank (<code>bank_mnemonic</code>)</label>
                                <input type="text" id="payBankMnemonic" class="form-control form-control-sm" value="1LINK">
                            </div>
                        </div>

                        <button type="button" class="btn btn-success w-100 fw-bold send-api-btn" data-endpoint="bill_payment">
                            <i class="fa-solid fa-check-double me-1"></i> Test Bill Payment API (Marks Bill PAID)
                        </button>
                    </div>

                    {{-- TAB 3: SEARCH PSID --}}
                    <div class="tab-pane fade" id="pane-search" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-info text-dark me-1">POST</span>
                                <code class="small">{{ url('/api/gb-pay/search-psid') }}</code>
                            </div>
                        </div>

                        <div class="mb-2">
                            <span class="small text-muted d-block mb-1" style="font-size: 11px;">Quick-fill from selected bill:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 quick-search-fill" data-by="cnic_no" style="font-size: 11px;">
                                    <i class="fa-regular fa-id-card me-1"></i> By CNIC
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 quick-search-fill" data-by="mobile_number" style="font-size: 11px;">
                                    <i class="fa-solid fa-mobile-screen me-1"></i> By Mobile
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 quick-search-fill" data-by="reference_number" style="font-size: 11px;">
                                    <i class="fa-solid fa-file-lines me-1"></i> By App No
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2 quick-search-fill" data-by="consumer_no" style="font-size: 11px;">
                                    <i class="fa-solid fa-barcode me-1"></i> By PSID
                                </button>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold mb-1">Search By (<code>search_by</code>) <span class="text-danger">*</span></label>
                                <select id="searchByField" class="form-select form-select-sm">
                                    <option value="cnic_no">cnic_no (Applicant CNIC)</option>
                                    <option value="mobile_number">mobile_number (Applicant Phone)</option>
                                    <option value="reference_number">reference_number (Application / Challan No)</option>
                                    <option value="consumer_no">consumer_no (PSID / Consumer No)</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small fw-bold mb-1">Search Value (<code>search_value</code>) <span class="text-danger">*</span></label>
                                <input type="text" id="searchValueField" class="form-control form-control-sm font-monospace"
                                    value="{{ $initialBill?->application?->applicant?->cnic ?? $initialBill?->psid ?? '' }}"
                                    placeholder="Enter CNIC, Mobile, App No, or PSID">
                            </div>
                        </div>

                        <button type="button" class="btn btn-info text-dark w-100 fw-bold send-api-btn" data-endpoint="search_psid">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Test Search PSID API
                        </button>
                    </div>

                    {{-- TAB 4: WEBHOOK SIMULATOR --}}
                    <div class="tab-pane fade" id="pane-webhook" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-dark me-1">POST</span>
                                <code class="small">{{ url('/gbpay/webhook') }}</code>
                            </div>
                            <span class="badge bg-light text-dark border" style="font-size: 10px;">HMAC Signature Auto-Attached</span>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-bold mb-1">20-Digit PSID (<code>psid</code>) <span class="text-danger">*</span></label>
                                <input type="text" id="whPsid" class="form-control form-control-sm font-monospace"
                                    value="{{ $initialBill?->psid ?? ($activePrefix . '0000000001') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Status (<code>status</code>)</label>
                                <select id="whStatus" class="form-select form-select-sm">
                                    <option value="paid" selected>paid</option>
                                    <option value="pending">pending</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold mb-1">Transaction ID</label>
                                <input type="text" id="whTransactionId" class="form-control form-control-sm font-monospace"
                                    value="WH-{{ now()->format('Ymd') }}-{{ rand(10000, 99999) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-1">Amount (Integer)</label>
                                <input type="number" step="1" min="1" id="whAmount" class="form-control form-control-sm"
                                    value="{{ $initialBill ? (int) ceil((float) $initialBill->bill_amount) : 3000 }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Bank</label>
                                <input type="text" id="whBankMnemonic" class="form-control form-control-sm" value="GBPay">
                            </div>
                        </div>

                        <button type="button" class="btn btn-dark w-100 fw-bold send-api-btn" data-endpoint="webhook">
                            <i class="fa-solid fa-satellite-dish me-1"></i> Trigger Webhook Notification
                        </button>
                    </div>
                </div>

                {{-- Live Request JSON & cURL Preview --}}
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-bold text-muted text-uppercase" style="font-size: 11px;">
                            <i class="fa-solid fa-code me-1"></i> Outgoing Request Payload Preview (JSON)
                        </span>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="btnCopyCurl" style="font-size: 11px;">
                            <i class="fa-solid fa-terminal me-1"></i> Copy cURL
                        </button>
                    </div>
                    <pre id="requestPayloadPreview" class="bg-dark text-light p-2 rounded small mb-0 font-monospace" style="font-size: 11.5px; max-height: 180px; overflow: auto;"></pre>
                </div>
            </div>
        </div>
    </div>

    {{-- RIGHT COLUMN: Live Response Inspector & Database State --}}
    <div class="col-lg-6">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-bolt text-success me-2"></i> Live API Response & State Inspector
                </h6>
                <div id="responseMetaBadges" class="d-none d-flex align-items-center gap-2">
                    <span id="respHttpBadge" class="badge bg-success">HTTP 200</span>
                    <span id="respCodeBadge" class="badge bg-primary">Code: 00</span>
                    <span id="respTimeBadge" class="badge bg-light text-dark border">0 ms</span>
                </div>
            </div>

            <div class="card-body">
                {{-- Empty State before first test --}}
                <div id="responseEmptyState" class="text-center py-5 text-muted">
                    <i class="fa-solid fa-flask-vial fs-1 text-primary opacity-50 mb-3 d-block"></i>
                    <h6 class="fw-bold text-dark">Ready to Test GBPay Endpoints</h6>
                    <p class="small mb-0 px-4">
                        Select a sample 20-digit PSID bill at the top and click <strong>Test Bill Inquiry API</strong>, <strong>Test Bill Payment API</strong>, or <strong>Test Search PSID API</strong> on the left to inspect the live response here.
                    </p>
                </div>

                {{-- Loading Spinner --}}
                <div id="responseLoadingState" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small fw-bold text-muted">Executing GBPay API request...</div>
                </div>

                {{-- Active Response Content --}}
                <div id="responseResultContainer" class="d-none">
                    {{-- 1. Endpoint & Status Banner --}}
                    <div id="respBanner" class="alert alert-success py-2 px-3 mb-3 small d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="respBannerTitle">200 OK — Inquiry Successful (00)</strong>
                            <div id="respBannerUrl" class="font-monospace" style="font-size: 11px;"></div>
                        </div>
                        <span id="respTimestamp" class="text-muted" style="font-size: 11px;"></span>
                    </div>

                    {{-- 2. Parsed Key-Value Summary Table --}}
                    <div class="mb-3">
                        <div class="small fw-bold text-dark mb-1" style="font-size: 11px; text-transform: uppercase;">
                            <i class="fa-solid fa-table-list text-primary me-1"></i> Parsed API Response Fields
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 small" id="parsedResponseTable">
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 3. Live Database Bill & Application Status After Call --}}
                    <div id="billStateBox" class="p-2 rounded bg-light border mb-3 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-bold text-dark" style="font-size: 11px; text-transform: uppercase;">
                                <i class="fa-solid fa-database text-info me-1"></i> Live Database Record State (After API Call)
                            </span>
                            <span id="dbBillStatusBadge" class="badge bg-warning text-dark">PENDING</span>
                        </div>
                        <div class="row g-1 small" style="font-size: 11.5px;" id="dbBillStateGrid"></div>
                    </div>

                    {{-- 4. Raw JSON Response Body --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-bold text-muted text-uppercase" style="font-size: 11px;">
                                <i class="fa-solid fa-Brackets-Curly me-1"></i> Raw JSON Response Body
                            </span>
                            <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="btnCopyResponseJson" style="font-size: 11px;">
                                <i class="fa-regular fa-copy me-1"></i> Copy JSON
                            </button>
                        </div>
                        <pre id="responseJsonViewer" class="bg-dark text-success p-3 rounded small mb-0 font-monospace" style="font-size: 12px; max-height: 260px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reference Card: GBPay Standard Response Codes --}}
<div class="card alf-panel shadow-sm">
    <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
        <span class="small fw-bold text-dark">
            <i class="fa-solid fa-book-open text-secondary me-1"></i> GBPay / 1Link Standard Response Codes Reference
        </span>
        <span class="small text-muted" style="font-size: 11px;">All bill amounts are integer values rounded to nearest upper integer</span>
    </div>
    <div class="card-body py-2">
        <div class="row g-2 small" style="font-size: 11.5px;">
            <div class="col-md-2"><span class="badge bg-success me-1">00</span> Successful Inquiry / Payment</div>
            <div class="col-md-2"><span class="badge bg-danger me-1">01</span> Bill / PSID Not Found</div>
            <div class="col-md-2"><span class="badge bg-danger me-1">02</span> Bill / Consumer Blocked</div>
            <div class="col-md-2"><span class="badge bg-warning text-dark me-1">03</span> Duplicate Transaction</div>
            <div class="col-md-2"><span class="badge bg-danger me-1">04</span> Username/Password Incorrect</div>
            <div class="col-md-2"><span class="badge bg-warning text-dark me-1">05 / 06</span> Validation / Already Paid</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const executeUrl = @json(route('admin.gbpay.test.execute'));
    const csrfToken = @json(csrf_token());

    const sampleSelector = document.getElementById('sampleBillSelector');
    const authModeSelect = document.getElementById('globalAuthMode');
    const resetBillForm = document.getElementById('resetBillForm');
    const sharedCredentialsBox = document.getElementById('sharedCredentialsBox');

    const apiUsername = document.getElementById('apiUsername');
    const apiPassword = document.getElementById('apiPassword');

    const inqVoucher = document.getElementById('inqVoucherNumber');
    const payVoucher = document.getElementById('payVoucherNumber');
    const payTxnId = document.getElementById('payTransactionId');
    const payAmount = document.getElementById('payTransactionAmount');
    const payBank = document.getElementById('payBankMnemonic');

    const searchByField = document.getElementById('searchByField');
    const searchValueField = document.getElementById('searchValueField');

    const whPsid = document.getElementById('whPsid');
    const whStatus = document.getElementById('whStatus');
    const whTxnId = document.getElementById('whTransactionId');
    const whAmount = document.getElementById('whAmount');
    const whBank = document.getElementById('whBankMnemonic');

    const requestPreview = document.getElementById('requestPayloadPreview');
    let currentEndpoint = 'bill_inquiry';

    function getSelectedBillOption() {
        if (!sampleSelector || sampleSelector.selectedIndex < 0) return null;
        return sampleSelector.options[sampleSelector.selectedIndex];
    }

    function buildPayload(endpoint) {
        if (endpoint === 'bill_inquiry') {
            return {
                username: apiUsername.value.trim(),
                password: apiPassword.value,
                voucher_number: inqVoucher.value.trim()
            };
        }
        if (endpoint === 'bill_payment') {
            return {
                username: apiUsername.value.trim(),
                password: apiPassword.value,
                voucher_number: payVoucher.value.trim(),
                transaction_id: payTxnId.value.trim(),
                transaction_amount: Math.ceil(Number(payAmount.value || 0)),
                bank_mnemonic: payBank.value.trim() || '1LINK'
            };
        }
        if (endpoint === 'search_psid') {
            return {
                username: apiUsername.value.trim(),
                password: apiPassword.value,
                search_by: searchByField.value,
                search_value: searchValueField.value.trim()
            };
        }
        return {
            psid: whPsid.value.trim(),
            status: whStatus.value,
            transaction_id: whTxnId.value.trim(),
            amount: Math.ceil(Number(whAmount.value || 0)),
            bank_mnemonic: whBank.value.trim() || 'GBPay',
            message: 'Payment received via GBPay Webhook'
        };
    }

    function updateRequestPreview() {
        const payload = buildPayload(currentEndpoint);
        requestPreview.textContent = JSON.stringify(payload, null, 2);
    }

    // Sync fields when selecting a sample bill
    if (sampleSelector) {
        sampleSelector.addEventListener('change', function () {
            const opt = getSelectedBillOption();
            if (!opt || !opt.value) return;

            const psid = opt.dataset.psid || opt.value;
            const amount = Math.ceil(Number(opt.dataset.amount || 3000));
            const resetUrl = opt.dataset.resetUrl;

            inqVoucher.value = psid;
            payVoucher.value = psid;
            payAmount.value = amount;
            whPsid.value = psid;
            whAmount.value = amount;

            if (searchByField.value === 'cnic_no' && opt.dataset.cnic) {
                searchValueField.value = opt.dataset.cnic;
            } else if (searchByField.value === 'mobile_number' && opt.dataset.phone) {
                searchValueField.value = opt.dataset.phone;
            } else if (searchByField.value === 'reference_number' && opt.dataset.appNo) {
                searchValueField.value = opt.dataset.appNo;
            } else {
                searchValueField.value = psid;
            }

            if (resetBillForm && resetUrl) {
                resetBillForm.action = resetUrl;
                resetBillForm.classList.remove('d-none');
            }

            updateRequestPreview();
        });
    }

    // Track tab switches
    document.querySelectorAll('#gbpayTestTabs button[data-endpoint]').forEach(function (tabBtn) {
        tabBtn.addEventListener('shown.bs.tab', function () {
            currentEndpoint = this.getAttribute('data-endpoint');
            if (sharedCredentialsBox) {
                sharedCredentialsBox.style.display = (currentEndpoint === 'webhook') ? 'none' : '';
            }
            updateRequestPreview();
        });
    });

    // Update preview on any input change
    document.querySelectorAll('#pane-inquiry input, #pane-payment input, #pane-search input, #pane-search select, #pane-webhook input, #pane-webhook select, #apiUsername, #apiPassword').forEach(function (el) {
        el.addEventListener('input', updateRequestPreview);
        el.addEventListener('change', updateRequestPreview);
    });

    // Randomize transaction ID button
    const btnRandomTxn = document.getElementById('btnRandomTxn');
    if (btnRandomTxn) {
        btnRandomTxn.addEventListener('click', function () {
            const rnd = Math.floor(100000 + Math.random() * 900000);
            payTxnId.value = '1LINK-{{ now()->format("Ymd") }}-' + rnd;
            updateRequestPreview();
        });
    }

    // Inquiry Presets
    document.querySelectorAll('.preset-inquiry').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const preset = this.dataset.preset;
            const opt = getSelectedBillOption();
            if (preset === 'valid') {
                authModeSelect.value = 'admin_bypass';
                if (opt && opt.dataset.psid) inqVoucher.value = opt.dataset.psid;
            } else if (preset === 'not_found') {
                authModeSelect.value = 'admin_bypass';
                inqVoucher.value = '10153009019999999999';
            } else if (preset === 'bad_auth') {
                authModeSelect.value = 'credentials';
                apiPassword.value = 'WrongPassword999!';
                if (opt && opt.dataset.psid) inqVoucher.value = opt.dataset.psid;
            }
            updateRequestPreview();
        });
    });

    // Payment Presets
    document.querySelectorAll('.preset-payment').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const preset = this.dataset.preset;
            const opt = getSelectedBillOption();
            if (preset === 'valid') {
                authModeSelect.value = 'admin_bypass';
                if (opt) {
                    payVoucher.value = opt.dataset.psid || opt.value;
                    payAmount.value = Math.ceil(Number(opt.dataset.amount || 3000));
                }
                payTxnId.value = '1LINK-{{ now()->format("Ymd") }}-' + Math.floor(100000 + Math.random() * 900000);
            } else if (preset === 'mismatch') {
                authModeSelect.value = 'admin_bypass';
                if (opt) {
                    payVoucher.value = opt.dataset.psid || opt.value;
                    payAmount.value = Math.ceil(Number(opt.dataset.amount || 3000)) + 500;
                }
            } else if (preset === 'not_found') {
                authModeSelect.value = 'admin_bypass';
                payVoucher.value = '10153009019999999999';
            }
            updateRequestPreview();
        });
    });

    // Search PSID Quick-Fill Chips
    document.querySelectorAll('.quick-search-fill').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const by = this.dataset.by;
            const opt = getSelectedBillOption();
            searchByField.value = by;
            if (opt) {
                if (by === 'cnic_no') searchValueField.value = opt.dataset.cnic || '';
                if (by === 'mobile_number') searchValueField.value = opt.dataset.phone || '';
                if (by === 'reference_number') searchValueField.value = opt.dataset.appNo || opt.dataset.challanNo || '';
                if (by === 'consumer_no') searchValueField.value = opt.dataset.psid || opt.value || '';
            }
            updateRequestPreview();
        });
    });

    // Execute API Test
    document.querySelectorAll('.send-api-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const endpoint = this.dataset.endpoint;
            const payload = buildPayload(endpoint);
            const authMode = authModeSelect.value;

            document.getElementById('responseEmptyState').classList.add('d-none');
            document.getElementById('responseResultContainer').classList.add('d-none');
            document.getElementById('responseLoadingState').classList.remove('d-none');

            try {
                const res = await fetch(executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        endpoint: endpoint,
                        payload: payload,
                        auth_mode: authMode
                    })
                });

                const data = await res.json();
                renderTestResult(data);
            } catch (err) {
                renderTestError(err);
            }
        });
    });

    function renderTestResult(data) {
        document.getElementById('responseLoadingState').classList.add('d-none');
        document.getElementById('responseResultContainer').classList.remove('d-none');
        document.getElementById('responseMetaBadges').classList.remove('d-none');

        const httpStatus = data.http_status || 200;
        const body = data.response_body || {};
        const respCode = body.response_code || (body.status === 'success' ? '00' : '—');
        const isSuccess = httpStatus >= 200 && httpStatus < 300 && (respCode === '00' || body.status === 'success');

        const httpBadge = document.getElementById('respHttpBadge');
        httpBadge.textContent = 'HTTP ' + httpStatus;
        httpBadge.className = 'badge ' + (httpStatus >= 200 && httpStatus < 300 ? 'bg-success' : 'bg-danger');

        const codeBadge = document.getElementById('respCodeBadge');
        codeBadge.textContent = 'Response Code: ' + respCode;
        codeBadge.className = 'badge ' + (respCode === '00' ? 'bg-primary' : (respCode === '06' ? 'bg-warning text-dark' : 'bg-danger'));

        document.getElementById('respTimeBadge').textContent = (data.elapsed_ms || 0) + ' ms';

        const banner = document.getElementById('respBanner');
        banner.className = 'alert py-2 px-3 mb-3 small d-flex justify-content-between align-items-center ' +
            (isSuccess ? 'alert-success' : (respCode === '06' ? 'alert-warning' : 'alert-danger'));

        const statusTitleMap = {
            '00': '00 — Success',
            '01': '01 — Bill / PSID Not Found',
            '02': '02 — Bill / Consumer Blocked',
            '03': '03 — Duplicate Transaction',
            '04': '04 — Username/Password Incorrect',
            '05': '05 — Validation / Amount Mismatch',
            '06': '06 — Bill Already Paid'
        };

        document.getElementById('respBannerTitle').textContent =
            'HTTP ' + httpStatus + ' — ' + (statusTitleMap[respCode] || body.message || 'API Response Received');
        document.getElementById('respBannerUrl').textContent = 'POST ' + (data.endpoint_url || '');
        document.getElementById('respTimestamp').textContent = data.timestamp || '';

        // Populate Parsed Key-Value Table
        const tbody = document.querySelector('#parsedResponseTable tbody');
        tbody.innerHTML = '';
        Object.keys(body).forEach(function (key) {
            const tr = document.createElement('tr');
            const val = typeof body[key] === 'object' ? JSON.stringify(body[key]) : body[key];
            tr.innerHTML = '<th class="bg-light text-muted" style="width: 38%;"><code>' + key + '</code></th>' +
                           '<td class="fw-semibold text-dark font-monospace">' + (val !== '' && val !== null ? val : '<span class="text-muted">""</span>') + '</td>';
            tbody.appendChild(tr);
        });

        // Populate Live Database State Box
        const billStateBox = document.getElementById('billStateBox');
        const dbGrid = document.getElementById('dbBillStateGrid');
        if (data.bill_state) {
            const bs = data.bill_state;
            billStateBox.classList.remove('d-none');
            const badge = document.getElementById('dbBillStatusBadge');
            badge.textContent = (bs.status || 'pending').toUpperCase();
            badge.className = 'badge ' + (bs.status === 'paid' ? 'bg-success' : 'bg-warning text-dark');

            dbGrid.innerHTML =
                '<div class="col-6"><strong>PSID:</strong> <code>' + bs.psid + '</code></div>' +
                '<div class="col-6"><strong>Bill Amount:</strong> Rs. ' + Number(bs.bill_amount).toLocaleString() + '</div>' +
                '<div class="col-6"><strong>Application:</strong> ' + (bs.application_no || '—') + '</div>' +
                '<div class="col-6"><strong>App Stage:</strong> <span class="text-primary fw-bold">' + (bs.application_stage || '—') + '</span></div>' +
                '<div class="col-6"><strong>Txn ID:</strong> <code>' + (bs.transaction_id || '—') + '</code></div>' +
                '<div class="col-6"><strong>Paid At:</strong> ' + (bs.paid_at || 'Not paid') + '</div>';
        } else {
            billStateBox.classList.add('d-none');
        }

        // Raw JSON
        document.getElementById('responseJsonViewer').textContent = JSON.stringify(body, null, 2);
    }

    function renderTestError(err) {
        document.getElementById('responseLoadingState').classList.add('d-none');
        document.getElementById('responseResultContainer').classList.remove('d-none');
        document.getElementById('responseJsonViewer').textContent = 'Error executing request: ' + (err.message || err);
    }

    // Copy JSON & Copy cURL buttons
    document.getElementById('btnCopyResponseJson')?.addEventListener('click', function () {
        navigator.clipboard.writeText(document.getElementById('responseJsonViewer').textContent);
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> Copied';
        setTimeout(() => { this.innerHTML = '<i class="fa-regular fa-copy me-1"></i> Copy JSON'; }, 1500);
    });

    document.getElementById('btnCopyCurl')?.addEventListener('click', function () {
        const payload = buildPayload(currentEndpoint);
        const urlMap = {
            bill_inquiry: @json(url('/api/gb-pay/bill-inquiry')),
            bill_payment: @json(url('/api/gb-pay/bill-payment')),
            search_psid: @json(url('/api/gb-pay/search-psid')),
            webhook: @json(url('/gbpay/webhook'))
        };
        const curlCmd = `curl -X POST "${urlMap[currentEndpoint]}" -H "Content-Type: application/json" -H "Accept: application/json" -d '${JSON.stringify(payload)}'`;
        navigator.clipboard.writeText(curlCmd);
        this.innerHTML = '<i class="fa-solid fa-check me-1"></i> cURL Copied';
        setTimeout(() => { this.innerHTML = '<i class="fa-solid fa-terminal me-1"></i> Copy cURL'; }, 1500);
    });

    updateRequestPreview();
});
</script>
@endpush
@endsection


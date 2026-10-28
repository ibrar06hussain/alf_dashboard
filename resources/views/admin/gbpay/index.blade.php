@extends('layouts.admin')

@section('title', 'GBPay Gateway & PSID Management')

@section('content')
@include('admin.partials.page-hero', [
    'title' => 'GBPay Gateway & PSID Integration (1Link)',
    'subtitle' => 'Configure 20-digit PSID prefix rules, manage GBPay Basic Auth API credentials, monitor paid & pending bills, and inspect webhook/API audit logs.',
    'icon' => 'fa-solid fa-money-check-dollar',
    'actions' => [
        ['label' => 'Challan Templates', 'url' => route('admin.challan-templates.index'), 'class' => 'btn btn-outline-secondary'],
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

{{-- Summary KPIs (Calculated strictly from bills table where status = paid per rules) --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card alf-panel shadow-sm border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total PSID Bills Generated</div>
                <div class="fs-3 fw-bold text-dark mt-1">{{ number_format($stats['total_bills']) }}</div>
                <div class="small text-muted">20-digit 1Link PSIDs issued</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card alf-panel shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Paid Bills</div>
                <div class="fs-3 fw-bold text-success mt-1">{{ number_format($stats['paid_bills']) }}</div>
                <div class="small text-muted">Verified via GBPay / Bank</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card alf-panel shadow-sm border-start border-4 border-warning h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Pending Bills</div>
                <div class="fs-3 fw-bold text-warning mt-1">{{ number_format($stats['pending_bills']) }}</div>
                <div class="small text-muted">Awaiting citizen payment</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card alf-panel shadow-sm border-start border-4 border-info h-100">
            <div class="card-body">
                <div class="text-muted small fw-bold text-uppercase">Total Paid Revenue (Bills)</div>
                <div class="fs-3 fw-bold text-info mt-1">Rs. {{ number_format($stats['total_paid_amount']) }}</div>
                <div class="small text-muted">Calculated from paid bills ledger</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Left Column: 20-Digit PSID Prefix & RCO/Head Config --}}
    <div class="col-lg-6">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-sliders text-primary me-2"></i> 20-Digit PSID Prefix & Treasury Configuration
                </h6>
                <span class="badge bg-success">Active Prefix: {{ ($psidConfig->one_link_prefix ?? '101325') . ($psidConfig->department_code ?? '18') . ($psidConfig->district_code ?? '01') . ($psidConfig->department_service_no ?? '01') }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.gbpay.psid-config.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">1Link Biller Prefix (6 Digits) <span class="text-danger">*</span></label>
                            <input type="text" name="one_link_prefix" maxlength="6" class="form-control form-control-sm"
                                value="{{ old('one_link_prefix', $psidConfig->one_link_prefix ?? '101325') }}" required>
                            <div class="form-text" style="font-size: 11px;">Standard GBPay 1Link prefix: <code>101325</code></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Department Code (2 Digits) <span class="text-danger">*</span></label>
                            <input type="text" name="department_code" maxlength="2" class="form-control form-control-sm"
                                value="{{ old('department_code', $psidConfig->department_code ?? '18') }}" required>
                            <div class="form-text" style="font-size: 11px;">2-digit department identifier (e.g. <code>18</code>)</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default District Code (2 Digits) <span class="text-danger">*</span></label>
                            <select name="district_code" class="form-select form-select-sm" required>
                                @foreach(\App\Models\PsidConfig::DISTRICT_CODES as $numCode => $info)
                                    <option value="{{ $numCode }}" @selected(old('district_code', $psidConfig->district_code ?? '01') === $numCode)>
                                        {{ $numCode }} — {{ $info['name'] }} ({{ $info['alpha'] }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text" style="font-size: 11px;">Automatically overridden per application's district if mapped.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Department Service No (1 Digit) <span class="text-danger">*</span></label>
                            <input type="text" name="department_service_no" maxlength="1" class="form-control form-control-sm"
                                value="{{ old('department_service_no', $psidConfig->department_service_no ?? '1') }}" required>
                            <div class="form-text" style="font-size: 11px;">Service number (e.g. <code>1</code>)</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default RCO Code <span class="text-danger">*</span></label>
                            <input type="text" name="rco" maxlength="20" class="form-control form-control-sm"
                                value="{{ old('rco', $psidConfig->rco ?? 'GL1800') }}" required>
                            <div class="form-text" style="font-size: 11px;">Reported in GBPay Bill Inquiry / Payment response</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Default Head of Account <span class="text-danger">*</span></label>
                            <input type="text" name="head" maxlength="20" class="form-control form-control-sm"
                                value="{{ old('head', $psidConfig->head ?? 'C03870') }}" required>
                            <div class="form-text" style="font-size: 11px;">Treasury head code (e.g. <code>C03870</code>)</div>
                        </div>

                        <div class="col-12">
                            <div class="p-2 rounded bg-light border small">
                                <strong>PSID Structure (20 Digits):</strong>
                                <code>[6-digit 1Link][2-digit Dept][2-digit District][1-digit Service][9-digit Unique Voucher ID]</code>
                                <div class="text-muted mt-1" style="font-size: 11px;">
                                    Example: <code>{{ ($psidConfig->one_link_prefix ?? '101530') . ($psidConfig->department_code ?? '15') . ($psidConfig->district_code ?? '01') . ($psidConfig->department_service_no ?? '1') }}000000001</code>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-sm btn-primary fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save PSID Configuration
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Right Column: GBPay API Credentials & Endpoints --}}
    <div class="col-lg-6">
        <div class="card alf-panel shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-key text-warning me-2"></i> GBPay API Basic Auth Credentials & Endpoints
                </h6>
                <span class="badge bg-info text-dark">HTTP Basic Auth</span>
            </div>
            <div class="card-body">
                <div class="p-2 rounded bg-light border small mb-3">
                    <div class="fw-bold text-dark mb-1"><i class="fa-solid fa-plug me-1 text-success"></i> GBPay Gateway Callback Endpoints</div>
                    <div style="font-size: 11px;">
                        <div><strong>Bill Inquiry:</strong> <code>POST {{ url('/api/gb-pay/bill-inquiry') }}</code></div>
                        <div><strong>Bill Payment:</strong> <code>POST {{ url('/api/gb-pay/bill-payment') }}</code></div>
                        <div><strong>Search PSID:</strong> <code>POST {{ url('/api/gb-pay/search-psid') }}</code></div>
                        <div><strong>Webhook:</strong> <code>POST {{ url('/gbpay/webhook') }}</code></div>
                    </div>
                </div>

                {{-- Existing Credentials Table --}}
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Last Used</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($credentials as $cred)
                                <tr>
                                    <td><code>{{ $cred->username }}</code></td>
                                    <td>{{ $cred->description ?: '—' }}</td>
                                    <td>
                                        @if($cred->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td style="font-size: 11px;">
                                        @if($cred->last_used_at)
                                            {{ $cred->last_used_at->format('d M Y H:i') }}
                                            <span class="text-muted d-block">{{ $cred->last_used_ip }}</span>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form action="{{ route('admin.gbpay.credentials.destroy', $cred) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this API credential?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-2">No credentials configured.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add New Credential Form --}}
                <form action="{{ route('admin.gbpay.credentials.store') }}" method="POST" class="border-top pt-3">
                    @csrf
                    <div class="fw-bold small mb-2">Add New GBPay Basic Auth Credential</div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="username" class="form-control form-control-sm" placeholder="Username" required>
                        </div>
                        <div class="col-md-4">
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Password (min 6)" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="Description (optional)">
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="cred_active" checked>
                                <label class="form-check-label small" for="cred_active">Active immediately</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-plus me-1"></i> Add Credential
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Bills & 20-Digit PSID Ledger --}}
<div class="card alf-panel shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> GBPay Bills & 20-Digit PSID Ledger
        </h6>
        <form method="GET" action="{{ route('admin.gbpay.index') }}" class="d-flex gap-2">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search PSID, App No, CNIC, Name..." style="min-width: 240px;">
            <select name="status" class="form-select form-select-sm" style="width: 140px;">
                <option value="">All Statuses</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            @if(request('q') || request('status'))
                <a href="{{ route('admin.gbpay.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>20-Digit PSID</th>
                        <th>Application</th>
                        <th>Applicant / CNIC</th>
                        <th>District</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>GBPay Txn ID / Paid At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        <tr>
                            <td>
                                <code class="fw-bold text-dark">{{ $bill->psid }}</code>
                            </td>
                            <td>
                                @if($bill->application)
                                    <a href="{{ route('admin.applications.show', $bill->application) }}" class="fw-bold text-decoration-none">
                                        {{ $bill->application->application_no }}
                                    </a>
                                    <span class="d-block text-muted" style="font-size: 11px;">Challan #{{ $bill->application->challan_no ?: '—' }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $bill->challan_data['applicant_name'] ?? ($bill->application?->applicant?->full_name ?? '—') }}</strong>
                                <span class="d-block text-muted" style="font-size: 11px;">{{ $bill->challan_data['cnic'] ?? ($bill->application?->applicant?->cnic ?? '—') }}</span>
                            </td>
                            <td>{{ $bill->application?->district?->name ?? '—' }}</td>
                            <td class="fw-bold">Rs. {{ number_format((float) $bill->bill_amount) }}</td>
                            <td>{{ $bill->due_date ? $bill->due_date->format('d M Y') : '—' }}</td>
                            <td>
                                @if($bill->status === 'paid')
                                    <span class="badge bg-success">PAID</span>
                                @elseif($bill->status === 'cancelled')
                                    <span class="badge bg-secondary">CANCELLED</span>
                                @else
                                    <span class="badge bg-warning text-dark">PENDING</span>
                                @endif
                            </td>
                            <td style="font-size: 11px;">
                                @if($bill->transaction_id)
                                    <code>{{ $bill->transaction_id }}</code>
                                @endif
                                @if($bill->paid_at)
                                    <span class="d-block text-success">{{ $bill->paid_at->format('d M Y H:i') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($bill->application)
                                    <a href="{{ route('applications.challan.download', $bill->application->application_no) }}" class="btn btn-sm btn-outline-primary py-0 px-2" target="_blank" title="Download Challan PDF">
                                        <i class="fa-solid fa-file-pdf"></i> Challan
                                    </a>
                                    <a href="{{ route('admin.applications.show', $bill->application) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="View Application">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No GBPay PSID bills found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($bills->hasPages())
        <div class="card-footer bg-white">
            {{ $bills->links() }}
        </div>
    @endif
</div>

{{-- Recent GBPay API & Webhook Audit Logs --}}
<div class="card alf-panel shadow-sm">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-terminal text-secondary me-2"></i> Recent GBPay API & Webhook Audit Logs (Last 25)
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Endpoint</th>
                        <th>PSID</th>
                        <th>HTTP Status</th>
                        <th>Result</th>
                        <th>Message / Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                        <tr>
                            <td style="font-size: 11px;">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td><code>{{ $log->request_type }}</code></td>
                            <td><code>{{ $log->psid }}</code></td>
                            <td><span class="badge bg-light text-dark border">{{ $log->status_code ?? '200' }}</span></td>
                            <td>
                                @if(in_array((string) $log->status_code, ['00', '200', '201'], true))
                                    <span class="badge bg-success">Success</span>
                                @else
                                    <span class="badge bg-secondary">{{ $log->status_code ?? 'Logged' }}</span>
                                @endif
                            </td>
                            <td style="font-size: 11px;">
                                {{ $log->status_message ?: ($log->response_data['message'] ?? 'OK') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">
                                No GBPay API or Webhook requests logged yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

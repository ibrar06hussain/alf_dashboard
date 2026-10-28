@extends('layouts.public')

@section('title', __('portal.track_title'))

@section('content')
<section class="breadcrumb-area alf-media-banner bg-img bg-overlay jarallax"
         data-alf-fit="bg"
         data-alf-w="{{ \App\Support\ImageSlots::get('breadcrumb')['width'] }}"
         data-alf-h="{{ \App\Support\ImageSlots::get('breadcrumb')['height'] }}"
         style="background-image: url('{{ \App\Support\Media::site('track_breadcrumb', 'frontend/img/bg-img/10.jpg') }}');">
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12">
                <div class="breadcrumb-text text-center">
                    <h2 class="text-white">{{ __('portal.track_title') }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">{{ __('portal.home') }}</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">{{ __('portal.track') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="border rounded-3 p-4 bg-white shadow-sm mb-4">
                    <h4 class="mb-3">{{ __('portal.find_application') }}</h4>
                    <p class="text-muted">{{ __('portal.track_blurb') }}</p>

                    <form method="post" action="{{ route('applications.track') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('portal.application_no') }}</label>
                            <input type="text" name="application_no" class="form-control @error('application_no') is-invalid @enderror"
                                   value="{{ old('application_no', request('application_no')) }}" placeholder="ALF-YYYYMMDD-XXXXXX" required>
                            @error('application_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('portal.cnic') }}</label>
                            <input type="text" name="cnic" class="form-control js-cnic @error('cnic') is-invalid @enderror"
                                   value="{{ old('cnic', request('cnic')) }}" maxlength="15" inputmode="numeric" placeholder="{{ __('portal.cnic_hint') }}" required>
                            @error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn famie-btn">{{ __('portal.track_status') }}</button>
                    </form>
                </div>

                @if(!empty($notFound))
                    <div class="alert alert-warning">{{ __('portal.track_not_found') }}</div>
                @endif

                @if($application)
                    <div class="border rounded-3 p-4 bg-white shadow-sm">
                        <h4 class="mb-3">{{ __('portal.application_status') }}</h4>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ __('portal.application_no') }}</dt>
                            <dd class="col-sm-8"><strong>{{ $application->application_no }}</strong></dd>

                            <dt class="col-sm-4">{{ __('portal.applicant') }}</dt>
                            <dd class="col-sm-8">{{ $application->applicant?->full_name }}</dd>

                            <dt class="col-sm-4">{{ __('portal.scheme') }}</dt>
                            <dd class="col-sm-8">{{ $application->scheme?->t("title") }}</dd>

                            <dt class="col-sm-4">{{ __('portal.department') }}</dt>
                            <dd class="col-sm-8">{{ $application->scheme?->department?->t("name") }}</dd>

                            <dt class="col-sm-4">{{ __('portal.district') }}</dt>
                            <dd class="col-sm-8">{{ $application->district?->t("name") }}</dd>

                            @if($application->applicant?->tehsil)
                                <dt class="col-sm-4">{{ __('portal.tehsil') }}</dt>
                                <dd class="col-sm-8">{{ $application->applicant->tehsil }}</dd>
                            @endif

                            @if($application->applicant?->union_council)
                                <dt class="col-sm-4">{{ __('portal.union_council') }}</dt>
                                <dd class="col-sm-8">{{ $application->applicant->union_council }}</dd>
                            @endif

                            @if($application->applicant?->village)
                                <dt class="col-sm-4">{{ __('portal.village') }}</dt>
                                <dd class="col-sm-8">{{ $application->applicant->village }}</dd>
                            @endif

                            <dt class="col-sm-4">{{ __('portal.submitted') }}</dt>
                            <dd class="col-sm-8">{{ optional($application->submitted_at)->format('d M Y, h:i A') }}</dd>

                            <dt class="col-sm-4">{{ __('portal.current_status') }}</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-primary">
                                    {{ $application->statusLabel() }}
                                </span>
                            </dd>

                            @if($application->status === 'rejected' && $application->rejection_reason)
                                <dt class="col-sm-4">{{ __('portal.rejection_reason') }}</dt>
                                <dd class="col-sm-8 text-danger fw-bold">{{ $application->rejection_reason }}</dd>
                            @endif

                            @if($application->current_stage === \App\Models\Application::STAGE_CORRECTION_REQUIRED)
                                <dt class="col-sm-4 text-warning">Required Correction</dt>
                                <dd class="col-sm-8 text-warning fw-bold">{{ $application->correction_remarks }}</dd>
                            @endif
                        </dl>

                        {{-- Citizen Actions: Challan Download & Payment Proof Upload --}}
                        @if($application->current_stage === \App\Models\Application::STAGE_CHALLAN_ISSUED && $application->challan_no)
                            @php
                                $challanBreakdown = $application->getChallanBreakdown();
                                $challanTpl = $challanBreakdown['template'];
                                $activeBill = $application->bills->firstWhere('status', 'pending') ?? $application->bills->first();
                                $psidNumber = $activeBill?->psid ?? $application->psid;
                                $psidConfig = \App\Models\PsidConfig::getActive();
                                $rcoCode = $challanTpl->rco_code ?: ($psidConfig->rco ?: 'GL1800');
                                $headCode = $challanTpl->head_code ?: ($psidConfig->head ?: 'C03870');
                            @endphp
                            <div class="alert alert-info mt-4 p-3 border-info">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-file-invoice-dollar me-1"></i> {{ $challanTpl->challan_title ?: 'FEE DEPOSIT CHALLAN' }}</h6>
                                        @if($psidNumber)
                                            <div class="p-2 mb-2 bg-white border border-primary rounded d-inline-flex align-items-center gap-2">
                                                <div>
                                                    <span class="small text-muted d-block" style="font-size: 11px;">GBPay / 1Link PSID (20 Digits)</span>
                                                    <strong class="text-dark font-monospace fs-6">{{ $psidNumber }}</strong>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary copy-psid-btn" data-psid="{{ $psidNumber }}">
                                                    <i class="fa-solid fa-copy me-1"></i> Copy PSID
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                    @if($psidNumber)
                                        <div class="bg-white p-1 border rounded text-center">
                                            {!! \App\Support\QrCodeGenerator::toSvg($psidNumber, 72, 2) !!}
                                            <div class="text-muted fw-bold" style="font-size: 9px;">Scan PSID</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="small mb-2">
                                    <div><strong>Department:</strong> {{ $challanTpl->department_name }}</div>
                                    <div><strong>Bank Name:</strong> {{ $challanTpl->bank_name }} (or Any 1Link / GBPay App) | <strong>Account No:</strong> {{ $challanTpl->account_no }}</div>
                                    <div><strong>Account Title:</strong> {{ $challanTpl->account_title }} | <strong>Account Head:</strong> {{ $challanTpl->account_head }}</div>
                                    <div><strong>RCO Code:</strong> {{ $rcoCode }} | <strong>Head Code:</strong> {{ $headCode }}</div>
                                    <div><strong>Challan No:</strong> #{{ $application->challan_no }} | <strong>Generated Date:</strong> {{ $application->challan_issued_at ? \Carbon\Carbon::parse($application->challan_issued_at)->format('d-M-Y') : date('d-M-Y') }}</div>
                                    <hr class="my-2">
                                    <div><strong>Cost of 1 Bag (including transport charges):</strong> Rs. {{ number_format((int) ceil($challanBreakdown['cost_of_1_bag'])) }} | <strong>Approved Bags:</strong> {{ (float) $challanBreakdown['approved_bags'] == (int) $challanBreakdown['approved_bags'] ? (int) $challanBreakdown['approved_bags'] : $challanBreakdown['approved_bags'] }}</div>
                                    <div><strong>Total Bill:</strong> Rs. {{ number_format($challanBreakdown['total_bill']) }} | <strong>Government Share ({{ (int) $challanBreakdown['govt_share_pct'] }}%):</strong> Rs. {{ number_format($challanBreakdown['govt_share']) }}</div>
                                    <div class="fw-bold text-dark mt-1">Final Payable Bill ({{ (int) $challanBreakdown['farmer_share_pct'] }}% Rounded): Rs. {{ number_format($challanBreakdown['final_bill']) }}/- ({{ $challanBreakdown['amount_words'] }})</div>
                                    <div class="text-muted mt-1"><strong>Directions:</strong> {{ $challanTpl->directions }}</div>
                                    <div class="text-primary fw-semibold mt-1"><i class="fa-solid fa-bolt me-1"></i> Pay online via GBPay / 1Link using the 20-digit PSID above for instant automatic verification, or deposit at the bank and upload your receipt below.</div>
                                </div>
                                <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
                                    <a href="{{ route('applications.challan.download', $application->application_no) }}" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-download me-1"></i> Download Fee Deposit Challan (PDF)
                                    </a>
                                </div>
                                <hr class="my-2">
                                <h6 class="small fw-bold mb-2">Upload Paid Bank Receipt (Optional Fallback for Manual Deposit)</h6>
                                <form method="POST" action="{{ route('applications.payment-proof.upload', $application->application_no) }}" enctype="multipart/form-data">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input type="file" name="payment_receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                        <button class="btn btn-success" type="submit"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Submit Receipt</button>
                                    </div>
                                    <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max: 5MB)</small>
                                </form>
                            </div>
                        @endif

                        @if($application->gbpay_transaction_id || $application->bills->where('status', 'paid')->isNotEmpty())
                            @php
                                $paidBill = $application->bills->firstWhere('status', 'paid');
                            @endphp
                            <div class="alert alert-success mt-4 p-3 border-success">
                                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-circle-check me-1"></i> GBPay Payment Verified</h6>
                                <div class="small mb-0">
                                    <div><strong>PSID:</strong> <span class="font-monospace">{{ $paidBill?->psid ?? $application->psid }}</span></div>
                                    <div><strong>Transaction ID:</strong> {{ $paidBill?->transaction_id ?? $application->gbpay_transaction_id }}</div>
                                    <div><strong>Amount Paid:</strong> Rs. {{ number_format((float) ($paidBill?->bill_amount ?? $application->challan_amount), 2) }}</div>
                                    @if($paidBill?->paid_at || $application->challan_paid_at)
                                        <div><strong>Paid Date:</strong> {{ \Carbon\Carbon::parse($paidBill?->paid_at ?? $application->challan_paid_at)->format('d M Y, h:i A') }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($application->current_stage === \App\Models\Application::STAGE_PAYMENT_PROOF_SUBMITTED)
                            <div class="alert alert-success mt-4 p-3">
                                <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-check-circle me-1"></i> Payment Proof Submitted</h6>
                                <p class="small mb-0">Your payment receipt has been received and is currently under verification with the Accounts Officer.</p>
                            </div>
                        @endif

                        @if($application->current_stage === \App\Models\Application::STAGE_CORRECTION_REQUIRED)
                            <div class="alert alert-warning mt-4 p-3 border-warning">
                                <h6 class="fw-bold text-dark mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> Correction Requested</h6>
                                <p class="small mb-3">The Deputy Director has requested updates: <em>"{{ $application->correction_remarks }}"</em></p>
                                <form method="POST" action="{{ route('applications.correction.submit', $application->application_no) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Correction Response / Clarifications</label>
                                        <textarea name="correction_notes" class="form-control form-control-sm" rows="3" placeholder="Provide requested corrections or clarifications here..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-warning"><i class="fa-solid fa-paper-plane me-1"></i> Resubmit for Review</button>
                                </form>
                            </div>
                        @endif

                        @if($application->statusLogs->isNotEmpty())
                            <hr>
                            <h5 class="mb-3">{{ __('portal.status_timeline') }}</h5>
                            <ul class="list-group list-group-flush">
                                @foreach($application->statusLogs as $log)
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between">
                                            <strong>{{ \App\Models\Application::translateStatus($log->to_status) }}</strong>
                                            <span class="small text-muted">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                        </div>
                                        @if($log->note)
                                            <div class="small text-muted">{{ $log->note }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-cnic').forEach(function (input) {
    input.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 13);
    });
});
document.querySelectorAll('.copy-psid-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const psid = this.getAttribute('data-psid');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(psid).then(function () {
                alert('PSID copied to clipboard: ' + psid);
            });
        }
    });
});
</script>
@endpush

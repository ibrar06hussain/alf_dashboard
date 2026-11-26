<?php

namespace App\Services;

use App\Exceptions\GbpayPaymentException;
use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\District;
use App\Models\GbpayResponse;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\VoucherSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GbpayService
{
    protected ?string $apiUrl;
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct()
    {
        $this->apiUrl = config('gbpay.api_url', env('GBPAY_API_URL'));
        $this->apiKey = config('gbpay.api_key', env('GBPAY_API_KEY'));
        $this->apiSecret = config('gbpay.api_secret', env('GBPAY_API_SECRET'));
    }

    /**
     * Generate unique 20-digit PSID with sequential 10-digit voucher serial number.
     */
    public function generatePsid(?Application $application = null, ?VoucherSerial &$allocatedSerial = null): string
    {
        return DB::transaction(function () use ($application, &$allocatedSerial) {
            $config = PsidConfig::getActive($application?->scheme_id);
            $districtDigits = $application?->district
                ? PsidConfig::resolveDistrictDigits($application->district)
                : null;

            $voucherSerial = VoucherSerial::allocateNext($application, $application?->challan_no);
            $psid = $config->generatePsid($districtDigits, $voucherSerial->serial_no);

            while (
                Bill::where('psid', $psid)->exists() ||
                Application::where('psid', $psid)->exists()
            ) {
                $voucherSerial = VoucherSerial::allocateNext($application, $application?->challan_no);
                $psid = $config->generatePsid($districtDigits, $voucherSerial->serial_no);
            }

            $voucherSerial->update(['psid' => $psid]);
            $allocatedSerial = $voucherSerial;

            return $psid;
        });
    }

    /**
     * Create or update a GBPay Bill with a 20-digit PSID for an Application.
     */
    public function createOrUpdateBillForApplication(
        Application $application,
        ?float $amount = null,
        bool $forceNew = false
    ): Bill {
        [$bill, $wasNewlyCreated] = DB::transaction(function () use ($application, $amount, $forceNew) {
            $lockedApp = Application::where('id', $application->id)
                ->with(['applicant', 'scheme.challanTemplate', 'district', 'items'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApp->isRejected()) {
                throw new \RuntimeException('Cannot create or sync a GBPay bill/PSID for a rejected application.');
            }

            $breakdown = $lockedApp->getChallanBreakdown();
            $template = $breakdown['template'];
            $psidConfig = PsidConfig::getActive($lockedApp->scheme_id);

            $rawBillAmount = $amount !== null
                ? (float) $amount
                : (float) ($lockedApp->challan_amount > 0 ? $lockedApp->challan_amount : $breakdown['final_bill']);
            $billAmount = (int) ceil($rawBillAmount);

            if ($billAmount <= 0) {
                throw new \RuntimeException('Cannot create or sync a GBPay bill/PSID for an application with Rs. 0 payable (all items free or 100% subsidized).');
            }

            // Lock existing bills inside transaction to prevent concurrent PSID minting
            $paidBill = $lockedApp->bills()->where('status', 'paid')->orderByDesc('bill_id')->lockForUpdate()->first();
            if ($paidBill) {
                if ($forceNew) {
                    throw new \RuntimeException("Cannot force a new PSID because application {$lockedApp->application_no} already has a PAID bill (PSID: {$paidBill->psid}).");
                }

                if ($lockedApp->psid !== $paidBill->psid) {
                    $lockedApp->psid = $paidBill->psid;
                    $lockedApp->saveQuietly();
                }

                return [$paidBill, false];
            }

            if ($forceNew && $lockedApp->isChallanPaid()) {
                throw new \RuntimeException("Cannot force a new PSID because application {$lockedApp->application_no} challan payment is already verified.");
            }

            $validityDays = (int) ($template->validity_days ?: 10);
            $dueDate = $lockedApp->challan_due_date
                ? $lockedApp->challan_due_date->toDateString()
                : now()->addDays($validityDays)->toDateString();

            $district = $lockedApp->district
                ?? $lockedApp->applicant?->district
                ?? ($lockedApp->district_id ? District::find($lockedApp->district_id) : null);
            $districtCode = $district?->code ?? PsidConfig::getDistrictAbbreviation(district: $district) ?? 'GL';

            $fallbackRco = ($psidConfig->scheme_id && $psidConfig->rco)
                ? $psidConfig->rco
                : ($template->rco_code ?: ($psidConfig->rco ?: 'GL-1618'));
            $rcoCode = PsidConfig::resolveDistrictRco($district, $fallbackRco);
            $headCode = ($psidConfig->scheme_id && $psidConfig->head)
                ? $psidConfig->head
                : ($template->head_code ?: ($psidConfig->head ?: 'C03870'));

            $challanData = [
                'applicant_name' => $lockedApp->applicant?->full_name ?? 'Applicant',
                'applicant_cnic' => $lockedApp->applicant?->cnic ?? '',
                'applicant_phone' => $lockedApp->applicant?->phone ?? '',
                'applicant_email' => $lockedApp->applicant?->email ?? '',
                'application_id' => $lockedApp->id,
                'application_no' => $lockedApp->application_no,
                'challan_no' => $lockedApp->challan_no,
                'scheme_title' => $lockedApp->scheme?->title ?? 'ALF Scheme',
                'district_name' => $districtCode,
                'approved_bags' => $breakdown['approved_bags'] ?? 1,
                'rco' => $rcoCode,
                'head' => $headCode,
            ];

            if (! $forceNew) {
                $pendingBill = $lockedApp->bills()->where('status', 'pending')->orderByDesc('bill_id')->lockForUpdate()->first();
                if ($pendingBill) {
                    $mergedChallanData = array_merge($challanData, array_filter([
                        'voucher_serial_no' => $pendingBill->voucher_serial,
                        'unique_voucher_id' => $pendingBill->formattedVoucherId(),
                    ], fn ($v) => $v !== null));

                    $pendingBill->update([
                        'bill_amount' => $billAmount,
                        'due_date' => $dueDate,
                        'challan_data' => $mergedChallanData,
                    ]);

                    $appDirty = false;
                    if ($lockedApp->psid !== $pendingBill->psid) {
                        $lockedApp->psid = $pendingBill->psid;
                        $appDirty = true;
                    }
                    if (! $lockedApp->isChallanPaid() && ($amount !== null || ! $lockedApp->challan_amount) && (int) ceil((float) ($lockedApp->challan_amount ?? 0)) !== $billAmount) {
                        $lockedApp->challan_amount = $billAmount;
                        $appDirty = true;
                    }
                    if ($appDirty) {
                        $lockedApp->saveQuietly();
                    }

                    return [$pendingBill, false];
                }
            } else {
                // Cancel previous pending bills when forcing a new PSID
                $lockedApp->bills()->where('status', 'pending')->update([
                    'status' => 'cancelled',
                ]);
            }

            if (! $lockedApp->challan_no) {
                $lockedApp->challan_no = 'CHL-' . now()->format('Y') . '-' . str_pad((string) $lockedApp->id, 6, '0', STR_PAD_LEFT);
            }

            $allocatedSerial = null;
            $psid = $this->generatePsid($lockedApp, $allocatedSerial);

            $challanData['challan_no'] = $lockedApp->challan_no;
            if ($allocatedSerial) {
                $challanData['voucher_serial_no'] = $allocatedSerial->serial_no;
                $challanData['unique_voucher_id'] = $allocatedSerial->voucher_code;
            }

            $bill = Bill::create([
                'application_id' => $lockedApp->id,
                'psid' => $psid,
                'voucher_serial' => $allocatedSerial?->serial_no,
                'bill_amount' => $billAmount,
                'status' => 'pending',
                'generated_at' => now(),
                'due_date' => $dueDate,
                'challan_data' => $challanData,
            ]);

            if ($allocatedSerial) {
                $allocatedSerial->update([
                    'bill_id' => $bill->bill_id,
                    'psid' => $psid,
                    'challan_no' => $lockedApp->challan_no,
                ]);
            }

            $lockedApp->psid = $psid;
            if (! $lockedApp->challan_amount) {
                $lockedApp->challan_amount = $billAmount;
            }
            if (! $lockedApp->challan_issued_at) {
                $lockedApp->challan_issued_at = now();
            }
            if (! $lockedApp->challan_due_date) {
                $lockedApp->challan_due_date = $dueDate;
            }
            $lockedApp->saveQuietly();

            return [$bill, true];
        });

        $application->refresh();

        if ($wasNewlyCreated) {
            // Register/log bill generation with GBPay
            $this->generateBill($bill);
        }

        return $bill;
    }

    /**
     * Generate bill via GBPay API (or record locally for GBPay pull-based inquiry).
     */
    public function generateBill(Bill $bill): array
    {
        try {
            $bill->loadMissing(['application.applicant', 'application.scheme.challanTemplate']);
            $psidConfig = PsidConfig::getActive($bill->application?->scheme_id);
            $template = $bill->application?->scheme?->getChallanTemplate();

            $district = $bill->application?->district ?? $bill->application?->applicant?->district ?? null;
            $fallbackRco = ($psidConfig->scheme_id && $psidConfig->rco)
                ? $psidConfig->rco
                : ($template?->rco_code ?: ($psidConfig->rco ?? 'GL-1618'));
            $rco = $bill->challan_data['rco'] ?? PsidConfig::resolveDistrictRco($district, $fallbackRco);
            $head = $bill->challan_data['head']
                ?? (($psidConfig->scheme_id && $psidConfig->head) ? $psidConfig->head : ($template?->head_code ?: ($psidConfig->head ?? 'C03870')));

            $requestData = [
                'psid' => $bill->psid,
                'amount' => (int) ceil((float) $bill->bill_amount),
                'description' => 'ALF Scheme Fee - Application No: ' . ($bill->application?->application_no ?? $bill->application_id),
                'customer_name' => $bill->application?->applicant?->full_name ?? 'Applicant',
                'customer_cnic' => $bill->application?->applicant?->cnic ?? '',
                'customer_email' => $bill->application?->applicant?->email ?? '',
                'customer_contact' => $bill->application?->applicant?->phone ?? '',
                'rco' => $rco,
                'head' => $head,
            ];

            if (empty($this->apiUrl) || empty($this->apiKey)) {
                GbpayResponse::create([
                    'bill_id' => $bill->bill_id,
                    'psid' => $bill->psid,
                    'request_type' => 'generate_bill',
                    'request_data' => $requestData,
                    'response_data' => ['status' => 'pending', 'message' => 'Bill generated locally for GBPay pull inquiry'],
                    'status_code' => '200',
                    'status_message' => 'Bill generated locally with PSID',
                ]);

                return [
                    'status' => 'success',
                    'message' => 'Bill generated locally with PSID.',
                    'psid' => $bill->psid,
                ];
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/generate-bill', $requestData);

            $responseData = $response->json() ?? ['error' => 'Invalid response'];

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'request_type' => 'generate_bill',
                'request_data' => $requestData,
                'response_data' => $responseData,
                'status_code' => (string) $response->status(),
                'status_message' => $responseData['message'] ?? ($response->successful() ? 'Success' : 'Error'),
            ]);

            if ($response->successful()) {
                $bill->update([
                    'gbpay_response' => $responseData,
                    'transaction_id' => $responseData['transaction_id'] ?? null,
                ]);

                return $responseData;
            }

            return [
                'status' => 'warning',
                'message' => 'Bill generated locally. API call returned: ' . ($responseData['message'] ?? 'Unknown response'),
                'psid' => $bill->psid,
            ];
        } catch (\Throwable $e) {
            Log::warning('GBPay Generate Bill Fallback', [
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'error' => $e->getMessage(),
            ]);

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'request_type' => 'generate_bill',
                'request_data' => $requestData ?? [],
                'response_data' => ['error' => $e->getMessage()],
                'status_code' => null,
                'status_message' => 'Bill generated locally (' . $e->getMessage() . ')',
            ]);

            return [
                'status' => 'warning',
                'message' => 'Bill generated locally with PSID.',
                'psid' => $bill->psid,
            ];
        }
    }

    /**
     * Check bill payment status via outbound GBPay API.
     */
    public function checkBillStatus(string $psid)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->get($this->apiUrl . '/check-status/' . $psid);

        $bill = Bill::where('psid', $psid)->first();

        if ($bill) {
            $responseData = $response->json() ?? [];

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $psid,
                'request_type' => 'check_status',
                'request_data' => ['psid' => $psid],
                'response_data' => $responseData,
                'status_code' => (string) $response->status(),
                'status_message' => $responseData['message'] ?? null,
            ]);

            if ($bill->status === 'pending' && $response->successful() && ($responseData['status'] ?? null) === 'paid') {
                $this->markBillAsPaid(
                    $bill,
                    (string) ($responseData['transaction_id'] ?? ('GBPAY-' . time())),
                    (float) ($responseData['amount'] ?? $bill->bill_amount),
                    (string) ($responseData['bank_mnemonic'] ?? 'GBPay'),
                    $responseData
                );
            }

            return $responseData;
        }

        return $response->json();
    }

    /**
     * Handle incoming webhook from GBPay.
     */
    public function handleWebhook(array $webhookData): bool
    {
        $psid = trim((string) ($webhookData['psid'] ?? $webhookData['voucher_number'] ?? ''));

        if ($psid === '') {
            throw new \InvalidArgumentException('PSID not found in webhook data');
        }

        $bill = Bill::with('application')
            ->where(function ($q) use ($psid) {
                $q->where('psid', $psid);
                if (strlen($psid) === 14) {
                    $q->orWhereRaw('LENGTH(psid) >= 14 AND SUBSTR(psid, -14) = ?', [$psid]);
                } elseif (strlen($psid) === 10) {
                    $q->orWhereRaw('LENGTH(psid) >= 10 AND SUBSTR(psid, -10) = ?', [$psid]);
                }
            })
            ->orderByDesc('bill_id')
            ->first();

        if (!$bill) {
            throw new \RuntimeException('Bill not found for PSID: ' . $psid);
        }

        $application = $bill->application;

        if ($application && $application->current_stage === Application::STAGE_CHALLAN_ISSUED && $application->isChallanExpired()) {
            app(\App\Services\ApplicationLifecycleService::class)->expireApplicationChallan($application);
            $application->refresh();
            $bill->refresh();
        }

        $isBlocked = $bill->status === 'cancelled'
            || ($application && ($application->isRejected() || $application->current_stage === Application::STAGE_DORMANT));

        GbpayResponse::create([
            'bill_id' => $bill->bill_id,
            'psid' => $bill->psid,
            'request_type' => 'webhook',
            'request_data' => [],
            'response_data' => $webhookData,
            'status_code' => $isBlocked ? '403' : (isset($webhookData['status_code']) ? (string) $webhookData['status_code'] : '200'),
            'status_message' => $isBlocked ? 'Bill/Consumer Blocked' : ($webhookData['message'] ?? 'Webhook received'),
        ]);

        if (($webhookData['status'] ?? null) === 'paid') {
            if ($isBlocked) {
                throw new GbpayPaymentException(
                    'Bill/Consumer Blocked: Payment is not accepted for cancelled, dormant, or rejected challan PSID.',
                    '02',
                    403
                );
            }

            $rawWebhookAmount = $webhookData['amount'] ?? $webhookData['transaction_amount'] ?? null;
            if (! is_numeric($rawWebhookAmount) || (float) $rawWebhookAmount <= 0) {
                throw new GbpayPaymentException('Transaction amount mismatch', '01', 400);
            }

            $transactionId = trim((string) ($webhookData['transaction_id'] ?? '')) ?: ('WH-' . uniqid());

            $this->markBillAsPaid(
                $bill,
                $transactionId,
                (float) $rawWebhookAmount,
                (string) ($webhookData['bank_mnemonic'] ?? 'GBPay'),
                $webhookData,
                true
            );
        }

        return true;
    }

    /**
     * Mark a Bill as paid and automatically verify & clear the linked Application.
     */
    public function markBillAsPaid(
        Bill $bill,
        string $transactionId,
        float $transactionAmount,
        string $bankMnemonic = 'GBPay',
        array $rawPayload = [],
        bool $strictAmountCheck = true
    ): Bill {
        return DB::transaction(function () use ($bill, $transactionId, $transactionAmount, $bankMnemonic, $rawPayload, $strictAmountCheck) {
            $lockedBill = Bill::where('bill_id', $bill->bill_id)->lockForUpdate()->firstOrFail();
            $lockedApp = $lockedBill->application_id
                ? Application::where('id', $lockedBill->application_id)->lockForUpdate()->first()
                : null;

            if (
                $lockedBill->status === 'cancelled'
                || ($lockedApp && ($lockedApp->isRejected() || $lockedApp->current_stage === Application::STAGE_DORMANT))
            ) {
                if ($lockedBill->status === 'pending' && $lockedApp?->isRejected()) {
                    $lockedBill->update(['status' => 'cancelled']);
                }

                throw new GbpayPaymentException('Bill/Consumer Blocked', '02', 403);
            }

            // Enforce atomic duplicate-transaction & already-paid idempotency inside lockForUpdate()
            if ($lockedBill->status === 'paid') {
                if ($transactionId !== '' && $lockedBill->transaction_id === $transactionId) {
                    throw new GbpayPaymentException('Duplicate Transaction', '03', 400);
                }

                throw new GbpayPaymentException('Already Paid', '06', 400);
            }

            // Enforce strict integer amount match against locked bill_amount
            $expectedIntAmount = (int) ceil((float) $lockedBill->bill_amount);
            $paidIntAmount = (int) ceil($transactionAmount);

            if ($strictAmountCheck && ($expectedIntAmount <= 0 || $paidIntAmount <= 0 || $paidIntAmount !== $expectedIntAmount)) {
                throw new GbpayPaymentException('Transaction amount mismatch', '01', 400);
            }

            $paidAt = now();

            $lockedBill->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'transaction_id' => $transactionId,
                'bank_mnemonic' => $bankMnemonic,
                'gbpay_response' => array_merge($lockedBill->gbpay_response ?? [], [
                    'payment_received' => array_merge([
                        'transaction_id' => $transactionId,
                        'transaction_amount' => $transactionAmount,
                        'bank_mnemonic' => $bankMnemonic,
                        'paid_at' => $paidAt->toDateTimeString(),
                    ], $rawPayload),
                ]),
            ]);

            $this->updateApplicationPaymentStatus($lockedBill, $transactionId, $bankMnemonic);

            return $lockedBill->fresh();
        });
    }

    /**
     * Update Application payment status & lifecycle stage based on paid bills in `bills` table.
     */
    public function updateApplicationPaymentStatus(
        Bill $bill,
        ?string $transactionId = null,
        string $bankMnemonic = 'GBPay'
    ): void {
        $application = Application::where('id', $bill->application_id)->lockForUpdate()->first();
        if (!$application) {
            return;
        }

        // Calculate total paid strictly from paid bills in the bills table
        $totalPaid = (int) ceil((float) $application->paidBills()->sum('bill_amount'));
        $requiredAmount = (int) ceil((float) ($application->challan_amount ?: $bill->bill_amount));

        if ($totalPaid <= 0 || $totalPaid < $requiredAmount) {
            return;
        }

        $fromStage = $application->current_stage ?: $application->status;
        $txnId = $transactionId ?: $bill->transaction_id;

        $eligibleForAutoClearance = in_array($fromStage, [
            Application::STAGE_CHALLAN_ISSUED,
            Application::STAGE_PAYMENT_PROOF_SUBMITTED,
            Application::STAGE_APPROVED,
            Application::STAGE_DORMANT,
            'directorate_approved',
        ], true);

        $updates = [
            'psid' => $bill->psid,
            'challan_paid_at' => $application->challan_paid_at ?? now(),
            'payment_verified_at' => $application->payment_verified_at ?? now(),
            'gbpay_transaction_id' => $txnId,
        ];

        if ($eligibleForAutoClearance) {
            $updates['current_stage'] = Application::STAGE_ELIGIBLE_BENEFICIARY;
            $updates['stage_updated_at'] = now();
            $updates['lock_version'] = ($application->lock_version ?? 1) + 1;
        }

        $application->update($updates);

        if ($eligibleForAutoClearance) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => $fromStage,
                'to_status' => Application::STAGE_PAYMENT_VERIFIED,
                'note' => "Payment received and verified automatically via GBPay ({$bankMnemonic}). PSID: {$bill->psid}, Transaction ID: {$txnId}, Paid: Rs. " . number_format($totalPaid),
            ]);

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'from_status' => Application::STAGE_PAYMENT_VERIFIED,
                'to_status' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                'note' => 'Beneficiary automatically confirmed eligible following verified GBPay payment.',
            ]);

            AccessAuditLog::log('gbpay_payment_verified', Application::class, (string) $application->id, [
                'psid' => $bill->psid,
                'bill_id' => $bill->bill_id,
                'transaction_id' => $txnId,
                'bank_mnemonic' => $bankMnemonic,
                'amount_paid' => $totalPaid,
                'from_stage' => $fromStage,
                'to_stage' => Application::STAGE_ELIGIBLE_BENEFICIARY,
            ]);
        }

        // Send automated SMS for GBPay Bill Payment Success (with bill paid amount & PSID)
        try {
            $freshApp = $application->fresh(['scheme.stockItems', 'applicant', 'district', 'bills']);
            if ($freshApp) {
                app(\App\Services\ZongSmsService::class)->sendForApplicationEvent($freshApp, 'gbpay_payment_success', [
                    '{amount_paid}' => number_format((float) $totalPaid, 0),
                    '{psid}' => (string) $bill->psid,
                    '{transaction_id}' => (string) ($txnId ?: $bill->transaction_id ?: 'N/A'),
                    '{payment_date}' => now()->format('d-M-Y H:i'),
                ]);
            }
        } catch (\Throwable $smsException) {
            Log::warning('GBPay payment success SMS dispatch failed', [
                'application_id' => $application->id,
                'psid' => $bill->psid,
                'error' => $smsException->getMessage(),
            ]);
        }
    }

    /**
     * Sync unpaid applications and pending GBPay bills when a Scheme's ChallanTemplate changes.
     */
    public function syncUnpaidApplicationsForTemplate(ChallanTemplate $template, array $additionalSchemeIds = []): int
    {
        $schemeIds = Scheme::query()
            ->where('challan_template_id', $template->id)
            ->when($template->is_default, fn ($q) => $q->orWhereNull('challan_template_id'))
            ->pluck('id')
            ->merge($additionalSchemeIds)
            ->unique()
            ->values();

        $updatedCount = 0;
        foreach (Scheme::whereIn('id', $schemeIds)->get() as $scheme) {
            $updatedCount += $this->syncUnpaidApplicationsForScheme($scheme);
        }

        return $updatedCount;
    }

    /**
     * Recalculate unpaid applications & pending PSID bills for a Scheme without touching paid/disbursed bills.
     */
    public function syncUnpaidApplicationsForScheme(Scheme $scheme): int
    {
        $scheme->load('challanTemplate');
        $template = $scheme->getChallanTemplate();
        $synced = 0;

        $appIds = Application::query()
            ->where('scheme_id', $scheme->id)
            ->whereNull('payment_verified_at')
            ->whereNull('gbpay_transaction_id')
            ->whereNull('challan_paid_at')
            ->whereNotIn('status', [Application::STATUS_REJECTED, Application::STATUS_DISBURSED])
            ->where(function ($q) {
                $q->whereNull('current_stage')
                    ->orWhereNotIn('current_stage', [
                        Application::STAGE_REJECTED,
                        Application::STAGE_DORMANT,
                        Application::STAGE_PAYMENT_VERIFIED,
                        Application::STAGE_FULLY_DISTRIBUTED,
                        Application::STAGE_COMPLETED,
                        Application::STAGE_DISBURSED,
                    ]);
            })
            ->whereDoesntHave('bills', fn ($bq) => $bq->where('status', 'paid'))
            ->pluck('id');

        foreach ($appIds as $appId) {
            $didSync = DB::transaction(function () use ($appId, $scheme, $template) {
                $lockedApp = Application::where('id', $appId)
                    ->with(['items', 'bills', 'scheme.challanTemplate', 'district', 'applicant'])
                    ->lockForUpdate()
                    ->first();

                if (! $lockedApp || $lockedApp->isChallanPaid() || $lockedApp->isRejected() || $lockedApp->current_stage === Application::STAGE_DORMANT) {
                    return false;
                }

                $changed = false;

                if ($lockedApp->items->isNotEmpty()) {
                    foreach ($lockedApp->items as $item) {
                        if (! $item->is_free && $item->inherits_template_share) {
                            $oldPayable = (float) $item->farmer_payable_amount;
                            $oldGovtPct = (float) $item->govt_share_pct;
                            $item->recalculateFinancials($template, false)->save();
                            if (abs($oldPayable - (float) $item->farmer_payable_amount) > 0.001 || abs($oldGovtPct - (float) $item->govt_share_pct) > 0.01) {
                                $changed = true;
                            }
                        }
                    }
                    $lockedApp->load('items');
                }

                $stage = $lockedApp->current_stage ?: $lockedApp->status;
                $isApprovedUnpaidStage = in_array($stage, [
                    Application::STAGE_CHALLAN_ISSUED,
                    Application::STAGE_APPROVED,
                    Application::STAGE_ELIGIBLE_BENEFICIARY,
                    'directorate_approved',
                ], true);

                if ($isApprovedUnpaidStage && (float) ($lockedApp->distributed_quantity ?? 0) <= 0) {
                    $breakdown = $lockedApp->getChallanBreakdown();
                    $newPayable = (int) ceil((float) ($breakdown['final_bill'] ?? 0));

                    if ($scheme->requires_challan && $newPayable > 0) {
                        $oldAmount = (int) ceil((float) ($lockedApp->challan_amount ?? 0));
                        $pendingBill = $lockedApp->bills->firstWhere('status', 'pending');

                        if ($stage !== Application::STAGE_CHALLAN_ISSUED || $oldAmount !== $newPayable || ! $pendingBill || (int) $pendingBill->bill_amount !== $newPayable) {
                            $validityDays = (int) (($template?->validity_days) ?: 10);
                            $lockedApp->challan_amount = $newPayable;
                            if (! $lockedApp->challan_no) {
                                $lockedApp->challan_no = 'CHL-' . date('Y') . '-' . str_pad((string) $lockedApp->id, 6, '0', STR_PAD_LEFT);
                            }
                            if (! $lockedApp->challan_issued_at) {
                                $lockedApp->challan_issued_at = now();
                            }
                            if (! $lockedApp->challan_due_date) {
                                $lockedApp->challan_due_date = now()->addDays($validityDays)->toDateString();
                            }
                            if ($stage !== Application::STAGE_CHALLAN_ISSUED) {
                                $lockedApp->current_stage = Application::STAGE_CHALLAN_ISSUED;
                                $lockedApp->status = 'directorate_approved';
                                $lockedApp->stage_updated_at = now();
                                $lockedApp->lock_version = ($lockedApp->lock_version ?? 1) + 1;
                            }
                            $lockedApp->saveQuietly();
                            $this->createOrUpdateBillForApplication($lockedApp, (float) $newPayable, false);

                            if ($stage !== Application::STAGE_CHALLAN_ISSUED) {
                                ApplicationStatusLog::create([
                                    'application_id' => $lockedApp->id,
                                    'from_status' => $stage,
                                    'to_status' => Application::STAGE_CHALLAN_ISSUED,
                                    'note' => "Challan generated/regenerated for Rs. {$newPayable} following scheme item pricing or template update.",
                                ]);
                            }

                            $changed = true;
                        }
                    } elseif ($stage !== Application::STAGE_ELIGIBLE_BENEFICIARY || $lockedApp->bills()->where('status', 'pending')->exists() || $lockedApp->psid || $lockedApp->challan_no) {
                        $lockedApp->bills()->where('status', 'pending')->update(['status' => 'cancelled']);
                        $lockedApp->update([
                            'current_stage' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                            'status' => 'directorate_approved',
                            'challan_no' => null,
                            'challan_amount' => null,
                            'challan_due_date' => null,
                            'challan_issued_at' => null,
                            'psid' => null,
                            'stage_updated_at' => now(),
                            'lock_version' => ($lockedApp->lock_version ?? 1) + 1,
                        ]);

                        ApplicationStatusLog::create([
                            'application_id' => $lockedApp->id,
                            'from_status' => $stage,
                            'to_status' => Application::STAGE_ELIGIBLE_BENEFICIARY,
                            'note' => 'Challan updated to Rs. 0 payable (all approved items free or 100% subsidized); pending challan/PSID cancelled and beneficiary promoted to Eligible Beneficiary.',
                        ]);
                        $changed = true;
                    }
                }

                return $changed;
            });

            if ($didSync) {
                $synced++;
            }
        }

        return $synced;
    }
}


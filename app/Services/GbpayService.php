<?php

namespace App\Services;

use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Bill;
use App\Models\GbpayResponse;
use App\Models\PsidConfig;
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
     * Generate unique 20-digit PSID for an application bill.
     */
    public function generatePsid(?Application $application = null): string
    {
        $config = PsidConfig::getActive();
        $districtDigits = $application?->district
            ? PsidConfig::resolveDistrictDigits($application->district)
            : null;

        $psid = $config->generatePsid($districtDigits);

        while (
            Bill::where('psid', $psid)->exists() ||
            Application::where('psid', $psid)->exists()
        ) {
            $psid = $config->generatePsid($districtDigits);
        }

        return $psid;
    }

    /**
     * Create or update a GBPay Bill with a 20-digit PSID for an Application.
     */
    public function createOrUpdateBillForApplication(
        Application $application,
        ?float $amount = null,
        bool $forceNew = false
    ): Bill {
        $application->loadMissing(['applicant', 'scheme.challanTemplate', 'district']);

        $breakdown = $application->getChallanBreakdown();
        $template = $breakdown['template'];
        $psidConfig = PsidConfig::getActive();

        $billAmount = $amount !== null && $amount > 0
            ? (float) ceil($amount)
            : (float) ($application->challan_amount > 0 ? $application->challan_amount : $breakdown['final_bill']);

        $validityDays = (int) ($template->validity_days ?: 10);
        $dueDate = $application->challan_due_date
            ? $application->challan_due_date->toDateString()
            : now()->addDays($validityDays)->toDateString();

        $rcoCode = $template->rco_code ?: ($psidConfig->rco ?: 'GL1800');
        $headCode = $template->head_code ?: ($psidConfig->head ?: 'C03870');

        $challanData = [
            'applicant_name' => $application->applicant?->full_name ?? 'Applicant',
            'applicant_cnic' => $application->applicant?->cnic ?? '',
            'applicant_phone' => $application->applicant?->phone ?? '',
            'applicant_email' => $application->applicant?->email ?? '',
            'application_id' => $application->id,
            'application_no' => $application->application_no,
            'challan_no' => $application->challan_no,
            'scheme_title' => $application->scheme?->title ?? 'ALF Scheme',
            'district_name' => $application->district?->name ?? 'Gilgit',
            'approved_bags' => $breakdown['approved_bags'] ?? 1,
            'rco' => $rcoCode,
            'head' => $headCode,
        ];

        if (! $forceNew) {
            // 1. Return existing paid bill if already paid
            $paidBill = $application->bills()->where('status', 'paid')->first();
            if ($paidBill) {
                if ($application->psid !== $paidBill->psid) {
                    $application->psid = $paidBill->psid;
                    $application->saveQuietly();
                }
                return $paidBill;
            }

            // 2. Reuse and sync existing pending bill if available
            $pendingBill = $application->bills()->where('status', 'pending')->first();
            if ($pendingBill) {
                $pendingBill->update([
                    'bill_amount' => $billAmount,
                    'due_date' => $dueDate,
                    'challan_data' => $challanData,
                ]);

                if ($application->psid !== $pendingBill->psid) {
                    $application->psid = $pendingBill->psid;
                    $application->saveQuietly();
                }

                return $pendingBill;
            }
        } else {
            // Cancel previous pending bills when forcing a new PSID
            $application->bills()->where('status', 'pending')->update([
                'status' => 'cancelled',
            ]);
        }

        $psid = $this->generatePsid($application);

        $bill = Bill::create([
            'application_id' => $application->id,
            'psid' => $psid,
            'bill_amount' => $billAmount,
            'status' => 'pending',
            'generated_at' => now(),
            'due_date' => $dueDate,
            'challan_data' => $challanData,
        ]);

        $application->psid = $psid;
        if (! $application->challan_no) {
            $application->challan_no = 'CHL-' . now()->format('Y') . '-' . str_pad((string) $application->id, 6, '0', STR_PAD_LEFT);
        }
        if (! $application->challan_amount) {
            $application->challan_amount = $billAmount;
        }
        if (! $application->challan_issued_at) {
            $application->challan_issued_at = now();
        }
        if (! $application->challan_due_date) {
            $application->challan_due_date = $dueDate;
        }
        $application->saveQuietly();

        // Register/log bill generation with GBPay
        $this->generateBill($bill);

        return $bill;
    }

    /**
     * Generate bill via GBPay API (or record locally for GBPay pull-based inquiry).
     */
    public function generateBill(Bill $bill): array
    {
        try {
            $bill->loadMissing(['application.applicant', 'application.scheme.challanTemplate']);
            $psidConfig = PsidConfig::getActive();
            $template = $bill->application?->scheme?->getChallanTemplate();

            $rco = $template?->rco_code ?: ($psidConfig->rco ?? 'GL1800');
            $head = $template?->head_code ?: ($psidConfig->head ?? 'C03870');

            $requestData = [
                'psid' => $bill->psid,
                'amount' => (float) $bill->bill_amount,
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

            if ($response->successful() && ($responseData['status'] ?? null) === 'paid') {
                $this->markBillAsPaid(
                    $bill,
                    (string) ($responseData['transaction_id'] ?? $bill->transaction_id ?? ('GBPAY-' . time())),
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
        $psid = $webhookData['psid'] ?? $webhookData['voucher_number'] ?? null;

        if (! $psid) {
            throw new \InvalidArgumentException('PSID not found in webhook data');
        }

        $bill = Bill::where('psid', $psid)->first();

        if (! $bill) {
            throw new \RuntimeException('Bill not found for PSID: ' . $psid);
        }

        GbpayResponse::create([
            'bill_id' => $bill->bill_id,
            'psid' => $psid,
            'request_type' => 'webhook',
            'request_data' => [],
            'response_data' => $webhookData,
            'status_code' => isset($webhookData['status_code']) ? (string) $webhookData['status_code'] : '200',
            'status_message' => $webhookData['message'] ?? 'Webhook received',
        ]);

        if (($webhookData['status'] ?? null) === 'paid') {
            $this->markBillAsPaid(
                $bill,
                (string) ($webhookData['transaction_id'] ?? $bill->transaction_id ?? ('WH-' . time())),
                (float) ($webhookData['amount'] ?? $bill->bill_amount),
                (string) ($webhookData['bank_mnemonic'] ?? 'GBPay'),
                $webhookData
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
        array $rawPayload = []
    ): void {
        DB::transaction(function () use ($bill, $transactionId, $transactionAmount, $bankMnemonic, $rawPayload) {
            $paidAt = now();

            $bill->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'transaction_id' => $transactionId,
                'bank_mnemonic' => $bankMnemonic,
                'gbpay_response' => array_merge($bill->gbpay_response ?? [], [
                    'payment_received' => array_merge([
                        'transaction_id' => $transactionId,
                        'transaction_amount' => $transactionAmount,
                        'bank_mnemonic' => $bankMnemonic,
                        'paid_at' => $paidAt->toDateTimeString(),
                    ], $rawPayload),
                ]),
            ]);

            $this->updateApplicationPaymentStatus($bill, $transactionId, $bankMnemonic);
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
        if (! $application) {
            return;
        }

        // Calculate total paid strictly from paid bills in the bills table
        $totalPaid = (float) $application->paidBills()->sum('bill_amount');
        $requiredAmount = (float) ($application->challan_amount ?: $bill->bill_amount);

        if ($totalPaid <= 0 || $totalPaid + 0.01 < $requiredAmount) {
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
                'note' => "Payment received and verified automatically via GBPay ({$bankMnemonic}). PSID: {$bill->psid}, Transaction ID: {$txnId}, Paid: Rs. " . number_format($totalPaid, 2),
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
    }
}


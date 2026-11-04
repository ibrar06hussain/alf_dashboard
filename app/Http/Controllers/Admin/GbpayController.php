<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\GbpayApiController;
use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\Bill;
use App\Models\GbpayApiCredential;
use App\Models\GbpayResponse;
use App\Models\PsidConfig;
use App\Services\GbpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GbpayController extends Controller
{
    /**
     * Display GBPay configuration, API credentials, bills ledger, and API logs.
     */
    public function index(Request $request): View
    {
        $psidConfig = PsidConfig::getActive();
        $credentials = GbpayApiCredential::orderByDesc('created_at')->get();

        $billsQuery = Bill::with(['application.applicant', 'application.scheme', 'application.district'])
            ->orderByDesc('bill_id');

        if ($request->filled('status')) {
            $billsQuery->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $billsQuery->where(function ($sub) use ($q) {
                $sub->where('psid', 'like', "%{$q}%")
                    ->orWhere('transaction_id', 'like', "%{$q}%")
                    ->orWhereHas('application', function ($aq) use ($q) {
                        $aq->where('application_no', 'like', "%{$q}%")
                            ->orWhere('challan_no', 'like', "%{$q}%")
                            ->orWhereHas('applicant', function ($apq) use ($q) {
                                $apq->where('full_name', 'like', "%{$q}%")
                                    ->orWhere('cnic', 'like', "%{$q}%")
                                    ->orWhere('phone', 'like', "%{$q}%");
                            });
                    });
            });
        }

        $bills = $billsQuery->paginate(15)->withQueryString();

        // Calculate summary metrics strictly from the bills table
        $stats = [
            'total_bills' => Bill::count(),
            'paid_bills' => Bill::where('status', 'paid')->count(),
            'pending_bills' => Bill::where('status', 'pending')->count(),
            'total_paid_amount' => (int) ceil((float) Bill::where('status', 'paid')->sum('bill_amount')),
            'total_pending_amount' => (int) ceil((float) Bill::where('status', 'pending')->sum('bill_amount')),
        ];

        $recentLogs = GbpayResponse::with('bill.application')
            ->orderByDesc('response_id')
            ->limit(25)
            ->get();

        return view('admin.gbpay.index', compact(
            'psidConfig',
            'credentials',
            'bills',
            'stats',
            'recentLogs'
        ));
    }

    /**
     * Update active PSID configuration.
     */
    public function updatePsidConfig(Request $request): RedirectResponse
    {
        if ($request->filled('department_code')) {
            $request->merge([
                'department_code' => str_pad(trim((string) $request->input('department_code')), 2, '0', STR_PAD_LEFT),
            ]);
        }

        if ($request->filled('department_service_no')) {
            $request->merge([
                'department_service_no' => str_pad(trim((string) $request->input('department_service_no')), 2, '0', STR_PAD_LEFT),
            ]);
        }

        $data = $request->validate([
            'one_link_prefix' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'department_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'district_code' => ['nullable', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'department_service_no' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'rco' => ['required', 'string', 'max:20'],
            'head' => ['required', 'string', 'max:20'],
        ]);

        $data['district_code'] = $data['district_code'] ?? '01';

        $config = PsidConfig::where('is_active', true)->first() ?? PsidConfig::first();
        if ($config) {
            $config->update(array_merge($data, ['is_active' => true]));
        } else {
            $config = PsidConfig::create(array_merge($data, ['is_active' => true]));
        }

        AccessAuditLog::log('gbpay_psid_config_updated', PsidConfig::class, (string) $config->config_id, $data);

        return back()->with('success', 'GBPay PSID configuration updated successfully.');
    }

    /**
     * Store a new GBPay API credential.
     */
    public function storeCredential(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:gbpay_api_credentials,username'],
            'password' => ['required', 'string', 'min:8'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $credential = GbpayApiCredential::create([
            'username' => $request->username,
            'password' => $request->password,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        AccessAuditLog::log('gbpay_credential_created', GbpayApiCredential::class, (string) $credential->credential_id, [
            'username' => $credential->username,
        ]);

        return back()->with('success', "GBPay API credential '{$credential->username}' created successfully.");
    }

    /**
     * Update an existing GBPay API credential.
     */
    public function updateCredential(Request $request, int $id): RedirectResponse
    {
        $credential = GbpayApiCredential::findOrFail($id);

        $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:gbpay_api_credentials,username,' . $id . ',credential_id'],
            'password' => ['nullable', 'string', 'min:8'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $updateData = [
            'username' => $request->username,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = $request->password;
        }

        $credential->update($updateData);

        AccessAuditLog::log('gbpay_credential_updated', GbpayApiCredential::class, (string) $credential->credential_id, [
            'username' => $credential->username,
            'is_active' => $credential->is_active,
        ]);

        return back()->with('success', "GBPay API credential '{$credential->username}' updated successfully.");
    }

    /**
     * Delete a GBPay API credential.
     */
    public function destroyCredential(int $id): RedirectResponse
    {
        $credential = GbpayApiCredential::findOrFail($id);
        $username = $credential->username;
        $credential->delete();

        AccessAuditLog::log('gbpay_credential_deleted', GbpayApiCredential::class, (string) $id, [
            'username' => $username,
        ]);

        return back()->with('success', "GBPay API credential '{$username}' deleted successfully.");
    }

    /**
     * Generate or sync a GBPay Bill & 20-digit PSID for a specific Application.
     */
    public function generateApplicationBill(
        Request $request,
        Application $application,
        GbpayService $gbpayService
    ): RedirectResponse {
        $forceNew = $request->boolean('force_new', false);

        $paidBill = $application->bills()->where('status', 'paid')->first();
        if ($paidBill) {
            return back()->with('error', "This application already has a PAID GBPay bill (PSID: {$paidBill->psid}).");
        }

        $bill = $gbpayService->createOrUpdateBillForApplication(
            $application,
            $request->filled('bill_amount') ? (float) ceil((float) $request->input('bill_amount')) : null,
            $forceNew
        );

        AccessAuditLog::log('gbpay_bill_generated', Application::class, (string) $application->id, [
            'bill_id' => $bill->bill_id,
            'psid' => $bill->psid,
            'bill_amount' => $bill->bill_amount,
            'force_new' => $forceNew,
        ]);

        return back()->with('success', "GBPay Bill generated/synced with 20-digit PSID: {$bill->psid}");
    }

    /**
     * Display the interactive GBPay API Testing & Sandbox Console.
     */
    public function testConsole(Request $request): View
    {
        $psidConfig = PsidConfig::getActive();
        $credentials = GbpayApiCredential::orderByDesc('is_active')->orderBy('username')->get();

        $sampleBills = Bill::with(['application.applicant', 'application.district', 'application.scheme'])
            ->orderByDesc('bill_id')
            ->limit(30)
            ->get();

        $sampleApplications = Application::with(['applicant', 'district', 'scheme'])
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $recentLogs = GbpayResponse::with('bill.application')
            ->orderByDesc('response_id')
            ->limit(15)
            ->get();

        return view('admin.gbpay.test', compact(
            'psidConfig',
            'credentials',
            'sampleBills',
            'sampleApplications',
            'recentLogs'
        ));
    }

    /**
     * Execute a live GBPay API or Webhook test request from the Admin Sandbox Console
     * and return the full HTTP response, timing, and updated bill/application state.
     */
    public function executeTest(
        Request $request,
        GbpayApiController $apiController,
        GbpayWebhookController $webhookController,
        GbpayService $gbpayService
    ): JsonResponse {
        $request->validate([
            'endpoint' => ['required', 'string', 'in:bill_inquiry,bill_payment,search_psid,webhook'],
            'payload' => ['required', 'array'],
            'auth_mode' => ['nullable', 'string', 'in:credentials,admin_bypass'],
        ]);

        $endpoint = (string) $request->input('endpoint');
        $payload = (array) $request->input('payload', []);
        $authMode = (string) $request->input('auth_mode', 'credentials');

        // Ensure transaction_amount / amount in test payload is rounded to nearest upper integer if numeric
        if (isset($payload['transaction_amount']) && is_numeric($payload['transaction_amount'])) {
            $payload['transaction_amount'] = (int) ceil((float) $payload['transaction_amount']);
        }
        if (isset($payload['amount']) && is_numeric($payload['amount'])) {
            $payload['amount'] = (int) ceil((float) $payload['amount']);
        }

        $endpointUrl = match ($endpoint) {
            'bill_inquiry' => url('/api/gb-pay/bill-inquiry'),
            'bill_payment' => url('/api/gb-pay/bill-payment'),
            'search_psid' => url('/api/gb-pay/search-psid'),
            'webhook' => url('/gbpay/webhook'),
        };

        // Construct internal request to pass to the target API controller
        $subRequest = Request::create(
            $endpointUrl,
            'POST',
            $payload,
            [],
            [],
            [
                'REMOTE_ADDR' => $request->ip(),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode($payload)
        );

        if ($authMode === 'admin_bypass') {
            $subRequest->attributes->set('gbpay_admin_test_bypass', true);
        }

        // If testing webhook and secret is configured, auto-sign so admin can test webhook cleanly
        if ($endpoint === 'webhook' && ! empty(config('gbpay.webhook_secret'))) {
            $sig = hash_hmac('sha256', (string) $subRequest->getContent(), (string) config('gbpay.webhook_secret'));
            $subRequest->headers->set('X-GBPay-Signature', $sig);
        }

        // Swap global request instance during dispatch so request() helper inside controllers sees $subRequest
        $originalRequest = app('request');
        $startTime = microtime(true);

        try {
            app()->instance('request', $subRequest);

            $apiResponse = match ($endpoint) {
                'bill_inquiry' => $apiController->billInquiry($subRequest),
                'bill_payment' => $apiController->billPayment($subRequest),
                'search_psid' => $apiController->searchPsid($subRequest),
                'webhook' => $webhookController->handle($subRequest, $gbpayService),
            };
        } finally {
            app()->instance('request', $originalRequest);
        }

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $apiResponse->getStatusCode();
        $responseBody = json_decode($apiResponse->getContent(), true) ?? ['raw' => $apiResponse->getContent()];

        // Look up affected Bill & Application state for live inspection
        $psidLookUp = $payload['voucher_number'] ?? $payload['psid'] ?? ($responseBody['psid'] ?? null);
        $billSummary = null;
        if ($psidLookUp) {
            $matchedBill = Bill::with(['application.applicant', 'application.district'])
                ->where('psid', $psidLookUp)
                ->orWhereRaw('RIGHT(psid, 14) = ?', [$psidLookUp])
                ->orWhereRaw('RIGHT(psid, 10) = ?', [$psidLookUp])
                ->orderByDesc('bill_id')
                ->first();

            if ($matchedBill) {
                $billSummary = [
                    'bill_id' => $matchedBill->bill_id,
                    'psid' => $matchedBill->psid,
                    'bill_amount' => (int) $matchedBill->bill_amount,
                    'status' => $matchedBill->status,
                    'transaction_id' => $matchedBill->transaction_id,
                    'bank_mnemonic' => $matchedBill->bank_mnemonic,
                    'paid_at' => $matchedBill->paid_at?->format('d M Y H:i:s'),
                    'due_date' => $matchedBill->due_date?->format('Y-m-d'),
                    'application_no' => $matchedBill->application?->application_no,
                    'application_stage' => $matchedBill->application?->statusLabel(),
                    'applicant_name' => $matchedBill->application?->applicant?->full_name,
                    'applicant_cnic' => $matchedBill->application?->applicant?->cnic,
                    'applicant_phone' => $matchedBill->application?->applicant?->phone,
                    'district' => $matchedBill->application?->district?->name,
                ];
            }
        }

        return response()->json([
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'http_status' => $statusCode,
            'elapsed_ms' => $elapsedMs,
            'endpoint' => $endpoint,
            'endpoint_url' => $endpointUrl,
            'auth_mode' => $authMode,
            'request_payload' => $payload,
            'response_body' => $responseBody,
            'bill_state' => $billSummary,
            'timestamp' => now()->format('d M Y H:i:s'),
        ]);
    }

    /**
     * Generate or refresh a sample GBPay Bill & 20-digit PSID for testing on the API Sandbox page.
     */
    public function createSampleBill(Request $request, GbpayService $gbpayService): RedirectResponse
    {
        $request->validate([
            'application_id' => ['nullable', 'exists:applications,id'],
            'bill_amount' => ['nullable', 'numeric', 'min:1'],
            'force_new' => ['nullable', 'boolean'],
        ]);

        $application = $request->filled('application_id')
            ? Application::findOrFail((int) $request->input('application_id'))
            : Application::orderByDesc('id')->first();

        if (! $application) {
            return back()->with('error', 'No applications exist in the database yet to generate a sample bill.');
        }

        $amount = $request->filled('bill_amount')
            ? (float) ceil((float) $request->input('bill_amount'))
            : null;

        $bill = $gbpayService->createOrUpdateBillForApplication(
            $application,
            $amount,
            $request->boolean('force_new', true)
        );

        return redirect()
            ->route('admin.gbpay.test', ['psid' => $bill->psid])
            ->with('success', "Sample Bill ready for testing! 20-Digit PSID: {$bill->psid} (Amount: Rs. " . number_format($bill->bill_amount) . ")");
    }

    /**
     * Reset a Bill back to 'pending' status so the admin can re-test Bill Inquiry & Bill Payment.
     */
    public function resetBillToPending(Bill $bill): RedirectResponse
    {
        $bill->update([
            'status' => 'pending',
            'paid_at' => null,
            'transaction_id' => null,
            'bank_mnemonic' => null,
        ]);

        if ($bill->application) {
            $bill->application->update([
                'challan_paid_at' => null,
                'payment_verified_at' => null,
                'gbpay_transaction_id' => null,
                'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            ]);
        }

        return redirect()
            ->route('admin.gbpay.test', ['psid' => $bill->psid])
            ->with('success', "Bill PSID {$bill->psid} has been reset to PENDING so you can re-test Inquiry and Payment.");
    }
}


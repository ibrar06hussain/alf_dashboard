<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\Bill;
use App\Models\GbpayApiCredential;
use App\Models\GbpayResponse;
use App\Models\PsidConfig;
use App\Services\GbpayService;
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
            'total_paid_amount' => (float) Bill::where('status', 'paid')->sum('bill_amount'),
            'total_pending_amount' => (float) Bill::where('status', 'pending')->sum('bill_amount'),
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
        $data = $request->validate([
            'one_link_prefix' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'department_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'district_code' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'department_service_no' => ['required', 'string', 'size:1', 'regex:/^\d$/'],
            'rco' => ['required', 'string', 'max:20'],
            'head' => ['required', 'string', 'max:20'],
        ]);

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
            $request->filled('bill_amount') ? (float) $request->input('bill_amount') : null,
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
}


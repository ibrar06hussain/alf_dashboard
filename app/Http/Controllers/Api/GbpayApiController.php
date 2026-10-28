<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Bill;
use App\Models\GbpayApiCredential;
use App\Models\GbpayResponse;
use App\Models\PsidConfig;
use App\Services\GbpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GbpayApiController extends Controller
{
    public function __construct(private GbpayService $gbpayService) {}

    /**
     * Authenticate GBPay API request using username & bcrypt password stored in gbpay_api_credentials.
     */
    protected function authenticate(string $username, string $password, ?string $ipAddress = null): GbpayApiCredential|false
    {
        $credential = GbpayApiCredential::getActiveByUsername($username);

        if (! $credential) {
            Log::warning('GBPay API authentication failed: Username not found', [
                'username' => $username,
                'ip' => $ipAddress,
            ]);
            return false;
        }

        if (! $credential->verifyPassword($password)) {
            Log::warning('GBPay API authentication failed: Invalid password', [
                'username' => $username,
                'ip' => $ipAddress,
            ]);
            return false;
        }

        $credential->updateLastUsed($ipAddress);

        Log::info('GBPay API authentication successful', [
            'username' => $username,
            'ip' => $ipAddress,
        ]);

        return $credential;
    }

    /**
     * Find Bill by 20-digit PSID or 14-digit voucher suffix.
     */
    protected function findBillByVoucher(string $voucherNumber): ?Bill
    {
        $psid = trim($voucherNumber);

        $bill = Bill::with(['application.applicant', 'application.district', 'application.scheme.challanTemplate'])
            ->where(function ($q) use ($psid) {
                if (strlen($psid) === 20) {
                    $q->where('psid', $psid);
                } elseif (strlen($psid) === 14) {
                    $q->whereRaw('RIGHT(psid, 14) = ?', [$psid]);
                } else {
                    $q->where('psid', $psid);
                }
            })
            ->orderByDesc('bill_id')
            ->first();

        if ($bill) {
            return $bill;
        }

        // Fallback: check if an Application has this psid directly
        $application = Application::with(['applicant', 'district', 'scheme.challanTemplate'])
            ->where(function ($q) use ($psid) {
                if (strlen($psid) === 20) {
                    $q->where('psid', $psid);
                } elseif (strlen($psid) === 14) {
                    $q->whereRaw('RIGHT(psid, 14) = ?', [$psid]);
                } else {
                    $q->where('psid', $psid);
                }
            })
            ->first();

        if ($application) {
            return $this->gbpayService->createOrUpdateBillForApplication($application);
        }

        return null;
    }

    /**
     * Bill Inquiry API
     * URL: POST /api/gb-pay/bill-inquiry
     */
    public function billInquiry(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'voucher_number' => 'required|string',
            ]);

            $credential = $this->authenticate($request->username, $request->password, $request->ip());
            if (! $credential) {
                return response()->json([
                    'response_code' => '04',
                    'message' => 'Username/password Incorrect',
                ], 401);
            }

            $psid = (string) $request->voucher_number;
            $bill = $this->findBillByVoucher($psid);

            if (! $bill) {
                return response()->json([
                    'response_code' => '01',
                    'message' => 'Bill Not Found',
                ], 404);
            }

            $application = $bill->application;

            // Check if bill is cancelled or application is rejected/dormant
            if (
                $bill->status === 'cancelled' ||
                in_array($application?->current_stage, [Application::STAGE_REJECTED, Application::STAGE_DORMANT], true) ||
                $application?->status === 'rejected'
            ) {
                return response()->json([
                    'response_code' => '02',
                    'message' => 'Bill/Consumer Blocked',
                ], 403);
            }

            // Check if already paid
            if ($bill->status === 'paid') {
                return response()->json([
                    'response_code' => '06',
                    'message' => 'Already Paid',
                    'date_paid' => $bill->paid_at ? $bill->paid_at->format('Y-m-d') : '',
                    'amount_paid' => (float) $bill->bill_amount,
                    'transaction_id' => $bill->transaction_id ?? '',
                ], 200);
            }

            $applicant = $application?->applicant;
            $district = $application?->district;
            $template = $application?->scheme?->getChallanTemplate();
            $psidConfig = PsidConfig::getActive();

            $districtCode = PsidConfig::getDistrictAbbreviation($psidConfig->district_code ?? '01', $district);
            $generatedAt = $bill->generated_at ?? $application?->challan_issued_at ?? now();
            $dueDate = $bill->due_date ?? $application?->challan_due_date ?? $generatedAt->copy()->addDays(10);

            $rcoCode = $template?->rco_code ?: ($psidConfig->rco ?? 'GL1800');
            $headCode = $template?->head_code ?: ($psidConfig->head ?? 'C03870');
            $payableAmount = (int) round((float) $bill->bill_amount);

            $responsePayload = [
                'response_code' => '00',
                'consumer_name' => $applicant?->full_name ?? 'Applicant',
                'district' => $districtCode,
                'consumer_number' => $applicant?->cnic ?? ($application?->application_no ?? (string) $bill->application_id),
                'departmental_charges' => 0,
                'due_date' => $dueDate->format('Y-m-d'),
                'payable_amount' => $payableAmount,
                'amount_within_due_date' => $payableAmount,
                'amount_after_due_date' => $payableAmount, // No late penalty or same amount within validity window
                'bill_month' => (int) $generatedAt->format('n'),
                'bill_year' => (int) $generatedAt->format('Y'),
                'date_paid' => '',
                'amount_paid' => '',
                'transaction_id' => '',
                'rco_code' => $rcoCode,
                'details' => [
                    [
                        'code' => $headCode,
                        'amount' => (float) $bill->bill_amount,
                    ],
                ],
            ];

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'request_type' => 'bill_inquiry',
                'request_data' => $request->except(['password']),
                'response_data' => $responsePayload,
                'status_code' => '200',
                'status_message' => 'Inquiry Successful',
            ]);

            return response()->json($responsePayload, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'response_code' => '05',
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GBPay Bill Inquiry Error', [
                'error' => $e->getMessage(),
                'request' => $request->except(['password']),
            ]);

            return response()->json([
                'response_code' => '99',
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * Bill Payment API
     * URL: POST /api/gb-pay/bill-payment
     */
    public function billPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'voucher_number' => 'required|string',
                'transaction_id' => 'required|string',
                'transaction_amount' => 'required|numeric',
                'bank_mnemonic' => 'required|string',
            ]);

            $credential = $this->authenticate($request->username, $request->password, $request->ip());
            if (! $credential) {
                return response()->json([
                    'response_code' => '04',
                    'message' => 'Username/password Incorrect',
                ], 401);
            }

            $psid = (string) $request->voucher_number;
            $transactionId = (string) $request->transaction_id;
            $transactionAmount = (float) $request->transaction_amount;
            $bankMnemonic = (string) $request->bank_mnemonic;

            $bill = $this->findBillByVoucher($psid);

            if (! $bill) {
                return response()->json([
                    'response_code' => '01',
                    'message' => 'Bill Not Found',
                ], 404);
            }

            $application = $bill->application;

            // Check if bill is blocked/cancelled
            if (
                $bill->status === 'cancelled' ||
                in_array($application?->current_stage, [Application::STAGE_REJECTED, Application::STAGE_DORMANT], true) ||
                $application?->status === 'rejected'
            ) {
                return response()->json([
                    'response_code' => '02',
                    'message' => 'Bill/Consumer Blocked',
                ], 403);
            }

            // Check if already paid
            if ($bill->status === 'paid') {
                if ($bill->transaction_id === $transactionId) {
                    return response()->json([
                        'response_code' => '03',
                        'message' => 'Duplicate Transaction',
                    ], 400);
                }

                return response()->json([
                    'response_code' => '06',
                    'message' => 'Already Paid',
                ], 400);
            }

            // Verify transaction amount matches bill amount
            if (abs($transactionAmount - (float) $bill->bill_amount) > 0.01) {
                return response()->json([
                    'response_code' => '05',
                    'message' => 'Transaction amount mismatch',
                ], 400);
            }

            // Mark bill as paid and auto-verify application
            $this->gbpayService->markBillAsPaid(
                $bill,
                $transactionId,
                $transactionAmount,
                $bankMnemonic,
                $request->except(['password'])
            );

            $consumerName = $application?->applicant?->full_name ?? 'Applicant';

            $responsePayload = [
                'response_code' => '00',
                'identification_parameter' => $consumerName,
            ];

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'request_type' => 'bill_payment',
                'request_data' => $request->except(['password']),
                'response_data' => $responsePayload,
                'status_code' => '200',
                'status_message' => 'Payment Successful',
            ]);

            return response()->json($responsePayload, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'response_code' => '05',
                'message' => 'Validation Error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GBPay Bill Payment Error', [
                'error' => $e->getMessage(),
                'request' => $request->except(['password']),
            ]);

            return response()->json([
                'response_code' => '99',
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * Search PSID API
     * URL: POST /api/gb-pay/search-psid
     */
    public function searchPsid(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'search_by' => 'required|string|in:reference_number,mobile_number,cnic_no,consumer_no,meter_number,vehicle_number',
                'search_value' => 'required|string',
            ]);

            $credential = $this->authenticate($request->username, $request->password, $request->ip());
            if (! $credential) {
                return response()->json([
                    'response_code' => '04',
                    'message' => 'Username/password Incorrect',
                ], 401);
            }

            $searchBy = (string) $request->search_by;
            $searchValue = trim((string) $request->search_value);

            $bill = null;

            switch ($searchBy) {
                case 'cnic_no':
                    $digits = preg_replace('/\D/', '', $searchValue);
                    $dashed = strlen($digits) === 13
                        ? substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1)
                        : $searchValue;

                    $applicantIds = Applicant::where('cnic', $digits)
                        ->orWhere('cnic', $dashed)
                        ->orWhere('cnic', $searchValue)
                        ->pluck('id');

                    if ($applicantIds->isNotEmpty()) {
                        $bill = Bill::whereHas('application', fn ($q) => $q->whereIn('applicant_id', $applicantIds))
                            ->orderByDesc('generated_at')
                            ->orderByDesc('bill_id')
                            ->first();

                        if (! $bill) {
                            $app = Application::whereIn('applicant_id', $applicantIds)
                                ->whereNotNull('challan_no')
                                ->orderByDesc('id')
                                ->first();
                            if ($app) {
                                $bill = $this->gbpayService->createOrUpdateBillForApplication($app);
                            }
                        }
                    }
                    break;

                case 'mobile_number':
                    $digits = preg_replace('/\D/', '', $searchValue);
                    $applicantIds = Applicant::where('phone', $searchValue)
                        ->orWhere('phone', $digits)
                        ->pluck('id');

                    if ($applicantIds->isNotEmpty()) {
                        $bill = Bill::whereHas('application', fn ($q) => $q->whereIn('applicant_id', $applicantIds))
                            ->orderByDesc('generated_at')
                            ->orderByDesc('bill_id')
                            ->first();

                        if (! $bill) {
                            $app = Application::whereIn('applicant_id', $applicantIds)
                                ->whereNotNull('challan_no')
                                ->orderByDesc('id')
                                ->first();
                            if ($app) {
                                $bill = $this->gbpayService->createOrUpdateBillForApplication($app);
                            }
                        }
                    }
                    break;

                case 'reference_number':
                case 'consumer_no':
                    $bill = Bill::where('psid', $searchValue)
                        ->orWhere('application_id', is_numeric($searchValue) ? (int) $searchValue : -1)
                        ->orWhereHas('application', function ($q) use ($searchValue) {
                            $q->where('application_no', $searchValue)
                                ->orWhere('challan_no', $searchValue)
                                ->orWhere('psid', $searchValue);
                        })
                        ->orderByDesc('generated_at')
                        ->orderByDesc('bill_id')
                        ->first();

                    if (! $bill) {
                        $app = Application::where('application_no', $searchValue)
                            ->orWhere('challan_no', $searchValue)
                            ->orWhere('psid', $searchValue)
                            ->first();
                        if ($app && ($app->challan_no || $app->current_stage === Application::STAGE_CHALLAN_ISSUED)) {
                            $bill = $this->gbpayService->createOrUpdateBillForApplication($app);
                        }
                    }
                    break;

                default:
                    return response()->json([
                        'response_code' => '05',
                        'message' => 'Invalid search parameter',
                    ], 400);
            }

            if (! $bill) {
                return response()->json([
                    'response_code' => '01',
                    'message' => 'PSID Not Found',
                ], 404);
            }

            $bill->loadMissing('application.applicant');
            $application = $bill->application;
            $applicant = $application?->applicant;
            $consumerName = $applicant?->full_name ?? 'Applicant';

            $billStatus = match ($bill->status) {
                'paid' => 'P',
                'cancelled' => 'C',
                default => 'U',
            };

            $generatedAt = $bill->generated_at ?? now();
            $dueDate = $bill->due_date ?? $application?->challan_due_date ?? $generatedAt->copy()->addDays(10);

            $responsePayload = [
                'response_code' => '00',
                'status' => true,
                'psid' => $bill->psid,
                'consumer_details' => $consumerName,
                'billing_month' => $generatedAt->format('F Y'),
                'due_date' => $dueDate->format('d-m-Y'),
                'payable_amount' => (float) $bill->bill_amount,
                'bill_status' => $billStatus,
            ];

            GbpayResponse::create([
                'bill_id' => $bill->bill_id,
                'psid' => $bill->psid,
                'request_type' => 'search_psid',
                'request_data' => $request->except(['password']),
                'response_data' => $responsePayload,
                'status_code' => '200',
                'status_message' => 'Search Successful',
            ]);

            return response()->json($responsePayload, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'response_code' => '05',
                'message' => 'Invalid search parameter',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('GBPay Search PSID Error', [
                'error' => $e->getMessage(),
                'request' => $request->except(['password']),
            ]);

            return response()->json([
                'response_code' => '99',
                'message' => 'Internal Server Error',
            ], 500);
        }
    }
}


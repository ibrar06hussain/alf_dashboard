<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GbpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GbpayWebhookController extends Controller
{
    /**
     * Handle GBPay webhook for payment status updates.
     */
    public function handle(Request $request, GbpayService $gbpayService): JsonResponse
    {
        try {
            if (! $this->verifyWebhookSignature($request)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid webhook signature',
                ], 401);
            }

            $webhookData = $request->all();

            Log::info('GBPay Webhook Received', [
                'data' => $webhookData,
                'ip' => $request->ip(),
            ]);

            $gbpayService->handleWebhook($webhookData);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('GBPay Webhook Error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify webhook signature if GBPAY_WEBHOOK_SECRET is configured.
     */
    protected function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('gbpay.webhook_secret');
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-GBPay-Signature') ?? $request->header('X-Webhook-Signature');
        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $signature);
    }
}


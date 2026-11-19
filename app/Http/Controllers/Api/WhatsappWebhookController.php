<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\WhatsappLog;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function __construct(private readonly WhatsappService $whatsappService) {}

    /**
     * Handle Meta WhatsApp Business Cloud API webhook verification (GET)
     * and incoming status / message events (POST).
     */
    public function handle(Request $request): Response
    {
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        return $this->receive($request);
    }

    private function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode', ''));
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token', ''));
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge', ''));

        $config = $this->whatsappService->getConfig();
        $expectedToken = trim((string) ($config['verify_token'] ?? ''));

        if ($mode === 'subscribe' && $expectedToken !== '' && $token !== '' && hash_equals($expectedToken, $token)) {
            return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'has_expected_token' => $expectedToken !== '',
            'ip' => $request->ip(),
        ]);

        return response('Forbidden', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function receive(Request $request): Response
    {
        $appSecret = trim((string) (SiteSetting::getValue('whatsapp_app_secret') ?: env('WHATSAPP_APP_SECRET', '')));
        if ($appSecret !== '') {
            $signature = (string) $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if ($signature === '' || ! hash_equals($expected, $signature)) {
                Log::warning('WhatsApp webhook signature mismatch', ['ip' => $request->ip()]);
                return response('Invalid signature', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
            }
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || empty($payload)) {
            return response('EVENT_RECEIVED', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // 1. Update delivery/read/failed statuses for outbound messages
                foreach ($value['statuses'] ?? [] as $statusItem) {
                    $wamid = (string) ($statusItem['id'] ?? '');
                    $deliveryState = strtolower(trim((string) ($statusItem['status'] ?? '')));
                    if ($wamid === '' || $deliveryState === '') {
                        continue;
                    }

                    $log = WhatsappLog::where('provider_message_id', $wamid)->first();
                    if ($log) {
                        $updates = [
                            'provider_response' => json_encode($statusItem),
                        ];

                        if ($deliveryState === 'failed') {
                            $errorCode = (string) ($statusItem['errors'][0]['code'] ?? 'FAILED');
                            $updates['status'] = 'failed';
                            $updates['provider_status_code'] = $errorCode;
                        } else {
                            $updates['provider_status_code'] = strtoupper($deliveryState);
                        }

                        $log->update($updates);
                    }
                }

                // 2. Log inbound messages cleanly for traceability
                foreach ($value['messages'] ?? [] as $inbound) {
                    Log::info('WhatsApp webhook inbound message received', [
                        'from' => $inbound['from'] ?? null,
                        'message_id' => $inbound['id'] ?? null,
                        'type' => $inbound['type'] ?? null,
                        'text' => $inbound['text']['body'] ?? null,
                    ]);
                }
            }
        }

        return response('EVENT_RECEIVED', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

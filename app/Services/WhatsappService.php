<?php

namespace App\Services;

use App\Models\Application;
use App\Models\SiteSetting;
use App\Models\WhatsappLog;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsappService
{
    public const DEFAULT_META_API_URL = 'https://graph.facebook.com/v20.0';

    public function __construct(private readonly ZongSmsService $smsService) {}

    /**
     * Load current WhatsApp API configuration from SiteSetting / .env fallback.
     */
    public function getConfig(): array
    {
        $enabledRaw = SiteSetting::getValue('whatsapp_enabled', env('WHATSAPP_ENABLED', '1'));
        $isEnabled = ! in_array(strtolower((string) $enabledRaw), ['0', 'false', 'off', 'no'], true);

        $rawApiUrl = rtrim((string) (SiteSetting::getValue('whatsapp_api_url', env('WHATSAPP_API_URL', self::DEFAULT_META_API_URL)) ?: self::DEFAULT_META_API_URL), '/');

        return [
            'enabled' => $isEnabled,
            'provider' => SiteSetting::getValue('whatsapp_provider', env('WHATSAPP_PROVIDER', 'meta_cloud')) ?: 'meta_cloud',
            'api_url' => $rawApiUrl,
            'phone_number_id' => trim((string) (SiteSetting::getValue('whatsapp_phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID', ''))),
            'business_account_id' => trim((string) (SiteSetting::getValue('whatsapp_business_account_id') ?: env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''))),
            'access_token' => trim((string) (SiteSetting::getValue('whatsapp_access_token') ?: env('WHATSAPP_ACCESS_TOKEN', ''))),
            'sender_number' => trim((string) (SiteSetting::getValue('whatsapp_sender_number') ?: env('WHATSAPP_SENDER_NUMBER', ''))),
            'verify_token' => trim((string) (SiteSetting::getValue('whatsapp_verify_token') ?: env('WHATSAPP_VERIFY_TOKEN', ''))),
        ];
    }

    public static function normalizePhone(?string $phone): string
    {
        return ZongSmsService::normalizePhone($phone);
    }

    public static function detectOperator(?string $phone): string
    {
        return ZongSmsService::detectOperator($phone);
    }

    public static function supportedOperators(): array
    {
        return ZongSmsService::supportedOperators();
    }

    /**
     * Trigger WhatsApp notifications for an Application lifecycle / payment event.
     *
     * @return WhatsappLog[]
     */
    public function sendForApplicationEvent(
        Application $application,
        string $eventKey,
        array $extraVars = [],
        ?int $sentBy = null
    ): array {
        if (! Schema::hasTable('whatsapp_templates') || ! Schema::hasTable('whatsapp_logs')) {
            return [];
        }

        $config = $this->getConfig();
        if (! $config['enabled']) {
            return [];
        }

        $application->loadMissing(['applicant', 'scheme.department', 'district', 'bills']);

        $phone = $application->applicant?->phone;
        if (empty($phone)) {
            return [];
        }

        $template = WhatsappTemplate::where('event_key', $eventKey)
            ->where('scheme_id', $application->scheme_id)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = WhatsappTemplate::where('event_key', $eventKey)
                ->whereNull('scheme_id')
                ->where('is_active', true)
                ->first();
        }

        if (! $template && $eventKey === Application::STAGE_CHALLAN_ISSUED) {
            $template = WhatsappTemplate::where('event_key', Application::STAGE_APPROVED)
                ->where(function ($q) use ($application) {
                    $q->where('scheme_id', $application->scheme_id)->orWhereNull('scheme_id');
                })
                ->where('is_active', true)
                ->orderByRaw('CASE WHEN scheme_id IS NOT NULL THEN 0 ELSE 1 END')
                ->first();
        }

        if (! $template) {
            return [];
        }

        $varsEn = $this->smsService->buildTemplateVariables($application, 'en', $extraVars);
        $varsUr = $this->smsService->buildTemplateVariables($application, 'ur', $extraVars);

        $logs = [];
        $langMode = $template->send_language ?: 'both';

        if (in_array($langMode, ['en', 'both'], true) && ! empty(trim((string) $template->message_en))) {
            $msgEn = $this->smsService->interpolate((string) $template->message_en, $varsEn);
            $logs[] = $this->sendMessage($phone, $msgEn, [
                'application_id' => $application->id,
                'whatsapp_template_id' => $template->id,
                'event_key' => $eventKey,
                'recipient_name' => $application->applicant?->full_name,
                'language' => 'en',
                'sent_by' => $sentBy,
            ]);
        }

        if (in_array($langMode, ['ur', 'both'], true) && ! empty(trim((string) $template->message_ur))) {
            $msgUr = $this->smsService->interpolate((string) $template->message_ur, $varsUr);
            $logs[] = $this->sendMessage($phone, $msgUr, [
                'application_id' => $application->id,
                'whatsapp_template_id' => $template->id,
                'event_key' => $eventKey,
                'recipient_name' => $application->applicant?->full_name,
                'language' => 'ur',
                'sent_by' => $sentBy,
            ]);
        }

        return $logs;
    }

    /**
     * Send a WhatsApp message via configured provider (Meta Cloud API, UltraMsg, or Custom HTTP Gateway)
     * and persist the audit record in `whatsapp_logs`.
     */
    public function sendMessage(
        string $phone,
        string $message,
        array $context = []
    ): WhatsappLog {
        $config = $this->getConfig();
        $destination = self::normalizePhone($phone);
        $operator = self::detectOperator($destination ?: $phone);
        $language = $context['language'] ?? (ZongSmsService::requiresUnicode($message) ? 'ur' : 'en');
        $provider = $config['provider'] ?? 'meta_cloud';

        // If gateway credentials are not yet configured, log in simulated mode
        $missingCreds = empty($config['access_token'])
            || ($provider === 'meta_cloud' && empty($config['phone_number_id']) && ! str_contains($config['api_url'], '/messages'));

        if ($missingCreds) {
            return WhatsappLog::create([
                'application_id' => $context['application_id'] ?? null,
                'whatsapp_template_id' => $context['whatsapp_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination ?: $phone,
                'recipient_name' => $context['recipient_name'] ?? null,
                'operator' => $operator,
                'language' => $language,
                'provider' => $provider,
                'message' => $message,
                'status' => 'simulated',
                'provider_status_code' => 'SIMULATED',
                'provider_message_id' => 'WA-SIM-' . strtoupper(uniqid()),
                'provider_response' => 'Simulated locally (WhatsApp API Access Token / Phone Number ID not configured)',
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        }

        try {
            if ($provider === 'ultramsg') {
                $result = $this->dispatchUltraMsg($config, $destination, $message);
            } elseif ($provider === 'custom_http') {
                $result = $this->dispatchCustomHttp($config, $destination, $message);
            } else {
                $result = $this->dispatchMetaCloud($config, $destination, $message);
            }

            return WhatsappLog::create([
                'application_id' => $context['application_id'] ?? null,
                'whatsapp_template_id' => $context['whatsapp_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination ?: $phone,
                'recipient_name' => $context['recipient_name'] ?? null,
                'operator' => $operator,
                'language' => $language,
                'provider' => $provider,
                'message' => $message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider_status_code' => (string) $result['status_code'],
                'provider_message_id' => $result['message_id'],
                'provider_response' => $result['raw_response'],
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp API exception while sending message', [
                'phone' => $destination,
                'error' => $e->getMessage(),
            ]);

            return WhatsappLog::create([
                'application_id' => $context['application_id'] ?? null,
                'whatsapp_template_id' => $context['whatsapp_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination ?: $phone,
                'recipient_name' => $context['recipient_name'] ?? null,
                'operator' => $operator,
                'language' => $language,
                'provider' => $provider,
                'message' => $message,
                'status' => 'failed',
                'provider_status_code' => 'EXCEPTION',
                'provider_message_id' => null,
                'provider_response' => $e->getMessage(),
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Dispatch via Meta WhatsApp Cloud API (Graph API v20.0+).
     */
    protected function dispatchMetaCloud(array $config, string $destination, string $message): array
    {
        $endpoint = str_ends_with($config['api_url'], '/messages')
            ? $config['api_url']
            : rtrim($config['api_url'], '/') . '/' . trim($config['phone_number_id'], '/') . '/messages';

        $response = Http::timeout(15)
            ->withToken($config['access_token'])
            ->acceptJson()
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $destination,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        $json = $response->json() ?? [];
        $msgId = $json['messages'][0]['id'] ?? ($json['message_id'] ?? ($json['id'] ?? null));
        $hasError = isset($json['error']);
        $success = $response->successful() && ! $hasError;

        return [
            'success' => $success,
            'status_code' => $hasError ? (string) ($json['error']['code'] ?? $response->status()) : (string) $response->status(),
            'message_id' => $msgId ? (string) $msgId : null,
            'raw_response' => $response->body(),
        ];
    }

    /**
     * Dispatch via UltraMsg / WaAPI style REST gateway.
     */
    protected function dispatchUltraMsg(array $config, string $destination, string $message): array
    {
        $endpoint = $config['api_url'];
        if (! empty($config['phone_number_id']) && ! str_contains($endpoint, $config['phone_number_id'])) {
            $endpoint = rtrim($endpoint, '/') . '/' . trim($config['phone_number_id'], '/') . '/messages/chat';
        }

        $response = Http::timeout(15)
            ->asForm()
            ->post($endpoint, [
                'token' => $config['access_token'],
                'to' => '+' . ltrim($destination, '+'),
                'body' => $message,
            ]);

        $json = $response->json() ?? [];
        $sentFlag = strtolower((string) ($json['sent'] ?? ''));
        $hasError = isset($json['error']);
        $success = $response->successful() && ! $hasError && ($sentFlag === 'true' || $sentFlag === '1' || isset($json['id']) || empty($json));

        return [
            'success' => $success,
            'status_code' => (string) $response->status(),
            'message_id' => isset($json['id']) ? (string) $json['id'] : null,
            'raw_response' => $response->body(),
        ];
    }

    /**
     * Dispatch via Custom HTTP / Webhook WhatsApp Gateway.
     */
    protected function dispatchCustomHttp(array $config, string $destination, string $message): array
    {
        $response = Http::timeout(15)
            ->withToken($config['access_token'])
            ->acceptJson()
            ->post($config['api_url'], [
                'to' => $destination,
                'phone' => $destination,
                'sender' => $config['sender_number'],
                'instance_id' => $config['phone_number_id'],
                'message' => $message,
                'body' => $message,
            ]);

        $json = $response->json() ?? [];
        $hasError = isset($json['error']) || (($json['success'] ?? true) === false);
        $msgId = $json['message_id'] ?? ($json['id'] ?? ($json['data']['id'] ?? null));

        return [
            'success' => $response->successful() && ! $hasError,
            'status_code' => (string) $response->status(),
            'message_id' => $msgId ? (string) $msgId : null,
            'raw_response' => $response->body(),
        ];
    }

    /**
     * Verify WhatsApp API connection / credentials status.
     */
    public function checkConnection(): array
    {
        $config = $this->getConfig();
        if (empty($config['access_token'])) {
            return [
                'success' => false,
                'message' => 'WhatsApp API Access Token / API Key is not configured yet. Running in local Simulation Mode.',
            ];
        }

        try {
            if (($config['provider'] ?? 'meta_cloud') === 'meta_cloud' && ! empty($config['phone_number_id'])) {
                $url = rtrim($config['api_url'], '/') . '/' . trim($config['phone_number_id'], '/');
                $response = Http::timeout(10)->withToken($config['access_token'])->get($url);
                if ($response->successful()) {
                    $data = $response->json() ?? [];
                    $displayPhone = $data['display_phone_number'] ?? ($config['sender_number'] ?: $config['phone_number_id']);
                    $verifiedName = $data['verified_name'] ?? 'WhatsApp Business API';
                    return [
                        'success' => true,
                        'message' => "Connected to {$verifiedName} ({$displayPhone}) [Provider: Meta Cloud API].",
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'WhatsApp Cloud API returned HTTP ' . $response->status() . ': ' . $response->body(),
                ];
            }

            return [
                'success' => true,
                'message' => "WhatsApp Gateway configured (Provider: {$config['provider']}, Endpoint: {$config['api_url']}). Ready to dispatch messages.",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}


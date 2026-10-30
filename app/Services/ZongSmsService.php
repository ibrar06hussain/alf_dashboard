<?php

namespace App\Services;

use App\Models\Application;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Support\Cnic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZongSmsService
{
    public const DEFAULT_REST_BASE_URL = 'https://cbs.zong.com.pk/reachrestapi/home';
    public const DEFAULT_SOAP_URL = 'https://cbs.zong.com.pk/ReachCWSv2/CorporateSMS.svc';

    /**
     * Load current Zong CBS configuration from SiteSetting / .env fallback.
     */
    public function getConfig(): array
    {
        $enabledRaw = SiteSetting::getValue('sms_enabled', env('ZONG_CBS_ENABLED', '1'));
        $isEnabled = ! in_array(strtolower((string) $enabledRaw), ['0', 'false', 'off', 'no'], true);

        $rawBaseUrl = rtrim((string) (SiteSetting::getValue('zong_cbs_base_url', env('ZONG_CBS_BASE_URL', self::DEFAULT_REST_BASE_URL)) ?: self::DEFAULT_REST_BASE_URL), '/');
        if (preg_match('#^https?://cbs\.zong\.com\.pk(/reach)?$#i', $rawBaseUrl)) {
            $rawBaseUrl = self::DEFAULT_REST_BASE_URL;
        }

        return [
            'enabled' => $isEnabled,
            'api_mode' => SiteSetting::getValue('zong_cbs_api_mode', env('ZONG_CBS_API_MODE', 'rest')) ?: 'rest',
            'base_url' => $rawBaseUrl,
            'soap_url' => rtrim((string) (SiteSetting::getValue('zong_cbs_soap_url', env('ZONG_CBS_SOAP_URL', self::DEFAULT_SOAP_URL)) ?: self::DEFAULT_SOAP_URL), '/'),
            'login_id' => trim((string) (SiteSetting::getValue('zong_cbs_login_id') ?: env('ZONG_CBS_LOGIN_ID', ''))),
            'login_password' => (string) (SiteSetting::getValue('zong_cbs_login_password') ?: env('ZONG_CBS_LOGIN_PASSWORD', '')),
            'mask' => trim((string) (SiteSetting::getValue('zong_cbs_mask') ?: env('ZONG_CBS_MASK', 'ALF-GB'))),
            'shortcode_preferred' => trim((string) (SiteSetting::getValue('zong_cbs_shortcode_preferred') ?: env('ZONG_CBS_SHORTCODE_PREFERRED', 'N'))) ?: 'N',
        ];
    }

    /**
     * Normalize Pakistani mobile number to 12-digit 923XXXXXXXXX format required by Zong CBS.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '92' . substr($digits, 1);
        } elseif (str_starts_with($digits, '3') && strlen($digits) === 10) {
            $digits = '92' . $digits;
        }

        return $digits;
    }

    /**
     * Detect if a message requires UniCode=1 (Urdu / non-ASCII / extended GSM_7 characters).
     */
    public static function requiresUnicode(string $message, string $language = 'en'): bool
    {
        if ($language === 'ur') {
            return true;
        }

        return (bool) preg_match('/[^\x20-\x7E\r\n]/', $message);
    }

    /**
     * Trigger SMS notifications for an Application lifecycle / payment event.
     * Supports scheme-specific override templates as well as global templates,
     * and dispatches in English, Urdu, or Both according to template configuration.
     *
     * @return SmsLog[]
     */
    public function sendForApplicationEvent(
        Application $application,
        string $eventKey,
        array $extraVars = [],
        ?int $sentBy = null
    ): array {
        $config = $this->getConfig();
        if (! $config['enabled']) {
            return [];
        }

        $application->loadMissing(['applicant', 'scheme.department', 'district', 'bills']);

        $phone = $application->applicant?->phone;
        if (empty($phone)) {
            return [];
        }

        // 1. Resolve active template: prefer scheme-specific template, fallback to global template
        $template = SmsTemplate::where('event_key', $eventKey)
            ->where('scheme_id', $application->scheme_id)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = SmsTemplate::where('event_key', $eventKey)
                ->whereNull('scheme_id')
                ->where('is_active', true)
                ->first();
        }

        // If eventKey is 'challan_issued' and no explicit 'challan_issued' template is active,
        // check 'approved' template (since approval with challan transitions to challan_issued)
        if (! $template && $eventKey === Application::STAGE_CHALLAN_ISSUED) {
            $template = SmsTemplate::where('event_key', Application::STAGE_APPROVED)
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

        $varsEn = $this->buildTemplateVariables($application, 'en', $extraVars);
        $varsUr = $this->buildTemplateVariables($application, 'ur', $extraVars);

        $logs = [];
        $langMode = $template->send_language ?: 'both';

        if (in_array($langMode, ['en', 'both'], true) && ! empty(trim((string) $template->message_en))) {
            $msgEn = $this->interpolate((string) $template->message_en, $varsEn);
            $logs[] = $this->sendQuickSms($phone, $msgEn, false, [
                'application_id' => $application->id,
                'sms_template_id' => $template->id,
                'event_key' => $eventKey,
                'recipient_name' => $application->applicant?->full_name,
                'language' => 'en',
                'sent_by' => $sentBy,
            ]);
        }

        if (in_array($langMode, ['ur', 'both'], true) && ! empty(trim((string) $template->message_ur))) {
            $msgUr = $this->interpolate((string) $template->message_ur, $varsUr);
            $logs[] = $this->sendQuickSms($phone, $msgUr, true, [
                'application_id' => $application->id,
                'sms_template_id' => $template->id,
                'event_key' => $eventKey,
                'recipient_name' => $application->applicant?->full_name,
                'language' => 'ur',
                'sent_by' => $sentBy,
            ]);
        }

        return $logs;
    }

    /**
     * Build key-value map of all supported template placeholders for an Application.
     */
    public function buildTemplateVariables(Application $application, string $locale = 'en', array $extraVars = []): array
    {
        $applicant = $application->applicant;
        $scheme = $application->scheme;
        $department = $scheme?->department;
        $district = $application->district;

        $cnicRaw = preg_replace('/\D+/', '', (string) ($applicant?->cnic ?? ''));
        $cnicDashed = Cnic::dashed($cnicRaw);

        $demandedQty = $application->getDemandedQuantity();
        $approvedQty = $application->getApprovedQuantity() ?? $demandedQty;
        $disbursedQty = (float) ($application->distributed_quantity ?: 0);

        $formatQty = fn ($val) => (float) $val == (int) $val ? (string) (int) $val : (string) round((float) $val, 2);

        $breakdown = $application->getChallanBreakdown();
        $challanAmount = (int) round((float) ($application->challan_amount > 0 ? $application->challan_amount : ($breakdown['final_bill'] ?? 0)));

        $latestBill = $application->bills?->sortByDesc('bill_id')->first();
        $psid = $application->psid ?: ($latestBill?->psid ?? 'N/A');
        $paidAmount = (int) round((float) ($extraVars['amount_paid'] ?? $latestBill?->bill_amount ?? $challanAmount));
        $txnId = (string) ($extraVars['transaction_id'] ?? $application->gbpay_transaction_id ?? $latestBill?->transaction_id ?? 'N/A');

        $dueDate = $application->challan_due_date
            ? $application->challan_due_date->format('d-M-Y')
            : ($latestBill?->due_date ? $latestBill->due_date->format('d-M-Y') : now()->addDays(10)->format('d-M-Y'));

        $schemeTitle = ($locale === 'ur' && ! empty($scheme?->title_ur))
            ? $scheme->title_ur
            : ($scheme?->title ?? 'ALF Scheme');

        $deptName = ($locale === 'ur' && ! empty($department?->name_ur))
            ? $department->name_ur
            : ($department?->name ?? 'Agriculture, Livestock & Fisheries Dept GB');

        $districtName = ($locale === 'ur' && ! empty($district?->name_ur))
            ? $district->name_ur
            : ($district?->name ?? 'GB');

        $vars = [
            '{name}' => $applicant?->full_name ?? 'Applicant',
            '{applicant_name}' => $applicant?->full_name ?? 'Applicant',
            '{father_name}' => $applicant?->father_name ?? '—',
            '{application_id}' => $application->application_no,
            '{application_no}' => $application->application_no,
            '{password}' => $cnicRaw ?: ($applicant?->cnic ?? ''),
            '{cnic_raw}' => $cnicRaw ?: ($applicant?->cnic ?? ''),
            '{cnic}' => $cnicDashed ?: ($applicant?->cnic ?? ''),
            '{phone}' => $applicant?->phone ?? '',
            '{scheme}' => $schemeTitle,
            '{scheme_ur}' => $scheme?->title_ur ?: ($scheme?->title ?? ''),
            '{department}' => $deptName,
            '{district}' => $districtName,
            '{tehsil}' => $applicant?->tehsil ?? '—',
            '{village}' => $applicant?->village ?? '—',
            '{status}' => $application->statusLabel(),
            '{demanded_bags}' => $formatQty($demandedQty),
            '{demanded_quantity}' => $formatQty($demandedQty),
            '{approved_bags}' => $formatQty($approvedQty),
            '{approved_quantity}' => $formatQty($approvedQty),
            '{disbursed_bags}' => $formatQty($disbursedQty),
            '{distributed_quantity}' => $formatQty($disbursedQty),
            '{challan_no}' => $application->challan_no ?: 'N/A',
            '{psid}' => $psid,
            '{challan_amount}' => number_format($challanAmount),
            '{bill_amount}' => number_format($challanAmount),
            '{amount_paid}' => number_format($paidAmount),
            '{bill_paid}' => number_format($paidAmount),
            '{transaction_id}' => $txnId,
            '{due_date}' => $dueDate,
            '{rejection_reason}' => $extraVars['rejection_reason'] ?? $application->rejection_reason ?? 'Administrative review criteria not met',
            '{correction_remarks}' => $extraVars['correction_remarks'] ?? $application->correction_remarks ?? 'Please review and update your application details',
            '{tracking_url}' => url('/track'),
        ];

        foreach ($extraVars as $k => $v) {
            if (is_scalar($v)) {
                $key = str_starts_with($k, '{') ? $k : '{' . $k . '}';
                if (! isset($vars[$key])) {
                    $vars[$key] = (string) $v;
                }
            }
        }

        return $vars;
    }

    /**
     * Replace placeholders in template text with actual values.
     */
    public function interpolate(string $templateText, array $variables): string
    {
        return strtr($templateText, $variables);
    }

    /**
     * Send an SMS via Zong 4G Corporate Bulk SMS (CBS) REST/SOAP API and log the result in `sms_logs`.
     */
    public function sendQuickSms(
        string $phone,
        string $message,
        ?bool $forceUnicode = null,
        array $context = []
    ): SmsLog {
        $config = $this->getConfig();
        $destination = self::normalizePhone($phone);
        $language = $context['language'] ?? 'en';
        $isUnicode = $forceUnicode ?? self::requiresUnicode($message, $language);

        // If gateway credentials are not yet configured, log in simulated mode without failing
        if (empty($config['login_id']) || empty($config['login_password'])) {
            return SmsLog::create([
                'application_id' => $context['application_id'] ?? null,
                'sms_template_id' => $context['sms_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination ?: $phone,
                'recipient_name' => $context['recipient_name'] ?? null,
                'language' => $language,
                'is_unicode' => $isUnicode,
                'mask' => $config['mask'],
                'message' => $message,
                'status' => 'simulated',
                'zong_status_code' => 'SIMULATED',
                'zong_sms_id' => 'SIM-' . strtoupper(uniqid()),
                'zong_response' => 'Simulated locally (Zong CBS Login ID / Password not configured)',
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        }

        try {
            if (($config['api_mode'] ?? 'rest') === 'soap') {
                $result = $this->dispatchSoapQuickSms($config, $destination, $message, $isUnicode);
            } elseif (($config['api_mode'] ?? 'rest') === 'rest_encoded') {
                $result = $this->dispatchRestEncodedQuickSms($config, $destination, $message, $isUnicode);
            } else {
                $result = $this->dispatchRestQuickSms($config, $destination, $message, $isUnicode);
            }

            return SmsLog::create([
                'application_id' => $context['application_id'] ?? null,
                'sms_template_id' => $context['sms_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination,
                'recipient_name' => $context['recipient_name'] ?? null,
                'language' => $language,
                'is_unicode' => $isUnicode,
                'mask' => $config['mask'],
                'message' => $message,
                'status' => $result['success'] ? 'sent' : 'failed',
                'zong_status_code' => $result['status_code'],
                'zong_sms_id' => $result['sms_id'],
                'zong_response' => $result['raw_response'],
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Zong CBS SMS dispatch failed', [
                'destination' => $destination,
                'event_key' => $context['event_key'] ?? 'manual',
                'error' => $e->getMessage(),
            ]);

            return SmsLog::create([
                'application_id' => $context['application_id'] ?? null,
                'sms_template_id' => $context['sms_template_id'] ?? null,
                'event_key' => $context['event_key'] ?? 'manual',
                'recipient_phone' => $destination ?: $phone,
                'recipient_name' => $context['recipient_name'] ?? null,
                'language' => $language,
                'is_unicode' => $isUnicode,
                'mask' => $config['mask'],
                'message' => $message,
                'status' => 'failed',
                'zong_status_code' => '999',
                'zong_sms_id' => null,
                'zong_response' => $e->getMessage(),
                'sent_by' => $context['sent_by'] ?? auth()->id(),
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Dispatch via Zong CBS REST API (/SendQuickSMS).
     * Spec Page 4:
     * POST https://cbs.zong.com.pk/reachrestapi/home/SendQuickSMS
     * Response: statusCode|description|SMS ID (e.g. 0|success|5220980151)
     */
    protected function dispatchRestQuickSms(array $config, string $destination, string $message, bool $isUnicode): array
    {
        $url = $config['base_url'] . '/SendQuickSMS';
        $payload = [
            'loginId' => $config['login_id'],
            'loginPassword' => $config['login_password'],
            'Destination' => $destination,
            'Mask' => $config['mask'],
            'Message' => $message,
            'UniCode' => $isUnicode ? '1' : '0',
            'ShortCodePrefered' => $config['shortcode_preferred'] ?: 'N',
        ];

        $response = Http::timeout(12)
            ->withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $rawBody = trim((string) $response->body(), " \t\n\r\0\x0B\"");

        return $this->parsePipeResponse($rawBody);
    }

    /**
     * Dispatch via Zong CBS REST Encoded API (/SendQuickSMSEncoded).
     * Spec Page 4-5: Base64 with Little Endian 16 (UTF-16LE) encoded password and message.
     */
    protected function dispatchRestEncodedQuickSms(array $config, string $destination, string $message, bool $isUnicode): array
    {
        $url = $config['base_url'] . '/SendQuickSMSEncoded';
        $encodedPassword = base64_encode(mb_convert_encoding($config['login_password'], 'UTF-16LE', 'UTF-8'));
        $encodedMessage = base64_encode(mb_convert_encoding($message, 'UTF-16LE', 'UTF-8'));

        $payload = [
            'loginId' => $config['login_id'],
            'loginPassword' => $encodedPassword,
            'Destination' => $destination,
            'Mask' => $config['mask'],
            'Message' => $encodedMessage,
            'UniCode' => $isUnicode ? '1' : '0',
            'ShortCodePrefered' => strtolower($config['shortcode_preferred'] ?: 'n'),
        ];

        $response = Http::timeout(12)
            ->withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $rawBody = trim((string) $response->body(), " \t\n\r\0\x0B\"");

        return $this->parsePipeResponse($rawBody);
    }

    /**
     * Dispatch via Zong CBS SOAP API (/QuickSMS).
     * Spec Page 11-12.
     */
    protected function dispatchSoapQuickSms(array $config, string $destination, string $message, bool $isUnicode): array
    {
        $url = $config['soap_url'];
        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:tem="http://tempuri.org/" '
            . 'xmlns:cbs="http://schemas.datacontract.org/2004/07/CBSCustomerAPI">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<tem:QuickSMS>'
            . '<tem:obj_QuickSMS>'
            . '<cbs:loginId>' . htmlspecialchars($config['login_id'], ENT_XML1) . '</cbs:loginId>'
            . '<cbs:loginPassword>' . htmlspecialchars($config['login_password'], ENT_XML1) . '</cbs:loginPassword>'
            . '<cbs:Destination>' . htmlspecialchars($destination, ENT_XML1) . '</cbs:Destination>'
            . '<cbs:Mask>' . htmlspecialchars($config['mask'], ENT_XML1) . '</cbs:Mask>'
            . '<cbs:Message>' . htmlspecialchars($message, ENT_XML1) . '</cbs:Message>'
            . '<cbs:UniCode>' . ($isUnicode ? '1' : '0') . '</cbs:UniCode>'
            . '<cbs:ShortCodePrefered>' . htmlspecialchars($config['shortcode_preferred'] ?: 'N', ENT_XML1) . '</cbs:ShortCodePrefered>'
            . '</tem:obj_QuickSMS>'
            . '</tem:QuickSMS>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $response = Http::timeout(15)
            ->withoutVerifying()
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"http://tempuri.org/ICorporateCBS/QuickSMS"',
            ])
            ->withBody($xml, 'text/xml; charset=utf-8')
            ->post($url);

        $raw = (string) $response->body();
        $isSuccess = str_contains(strtolower($raw), 'submitted successfully');
        $smsId = null;
        if (preg_match('/id\s*:\s*(\d+)/i', $raw, $m)) {
            $smsId = $m[1];
        }

        return [
            'success' => $isSuccess,
            'status_code' => $isSuccess ? '0' : (string) $response->status(),
            'description' => $isSuccess ? 'Submitted Successfully' : 'SOAP Error',
            'sms_id' => $smsId,
            'raw_response' => $raw,
        ];
    }

    /**
     * Parse Zong CBS pipe-delimited response: statusCode|description|smsId
     * Example: 0|success|5220980151
     */
    public function parsePipeResponse(string $rawBody): array
    {
        $parts = explode('|', $rawBody);
        $statusCode = trim($parts[0] ?? '');
        $description = trim($parts[1] ?? $rawBody);
        $smsId = isset($parts[2]) ? trim($parts[2]) : null;

        $success = ($statusCode === '0');

        return [
            'success' => $success,
            'status_code' => $statusCode !== '' ? $statusCode : 'UNKNOWN',
            'description' => $description,
            'sms_id' => $smsId,
            'raw_response' => $rawBody,
        ];
    }

    /**
     * Query Zong CBS Account Balance via REST API (/GetBalance & /GetCurrentBalance).
     * Spec Page 2-4.
     */
    public function getBalance(): array
    {
        $config = $this->getConfig();
        if (empty($config['login_id']) || empty($config['login_password'])) {
            return [
                'success' => false,
                'message' => 'Zong CBS Login ID and Password are not configured yet.',
                'raw' => null,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->acceptJson()
                ->asJson()
                ->post($config['base_url'] . '/GetBalance', [
                    'loginId' => $config['login_id'],
                    'loginPassword' => $config['login_password'],
                ]);

            $raw = trim((string) $response->body(), " \t\n\r\0\x0B\"");
            $parts = explode('|', $raw);
            $statusCode = trim($parts[0] ?? '');

            if ($statusCode === '0') {
                return [
                    'success' => true,
                    'status_code' => '0',
                    'description' => $parts[1] ?? 'success',
                    'total_sms_balance' => $parts[2] ?? '0',
                    'rate' => $parts[3] ?? '0',
                    'expiry' => $parts[4] ?? '—',
                    'raw' => $raw,
                ];
            }

            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => SmsLog::zongCodeDescription($statusCode) . ' (' . $raw . ')',
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to reach Zong CBS API: ' . $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Query delivery status of a specific SMS via Zong CBS REST API (/GetStatus).
     * Spec Page 5.
     */
    public function getMessageStatus(string $destination, string $smsId): array
    {
        $config = $this->getConfig();
        if (empty($config['login_id']) || empty($config['login_password'])) {
            return [
                'success' => false,
                'message' => 'Zong CBS credentials not configured.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->asJson()
                ->post($config['base_url'] . '/GetStatus', [
                    'loginId' => $config['login_id'],
                    'loginPassword' => $config['login_password'],
                    'Destination' => self::normalizePhone($destination),
                    'smsId' => $smsId,
                ]);

            $raw = trim((string) $response->body(), " \t\n\r\0\x0B\"");
            $parts = explode('|', $raw);
            $statusCode = trim($parts[0] ?? '');

            return [
                'success' => $statusCode === '0',
                'status_code' => $statusCode,
                'description' => $parts[1] ?? $raw,
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\ZongSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsTemplateController extends Controller
{
    public function __construct(private readonly ZongSmsService $smsService) {}

    public function index(Request $request): View
    {
        $templates = SmsTemplate::with('scheme')
            ->withCount('logs')
            ->orderBy('id')
            ->get();

        $logsQuery = SmsLog::with(['application', 'template', 'sender'])->latest();

        if ($request->filled('event_key')) {
            $logsQuery->where('event_key', $request->string('event_key'));
        }
        if ($request->filled('status')) {
            $logsQuery->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $logsQuery->where(function ($sub) use ($q) {
                $sub->where('recipient_phone', 'like', "%{$q}%")
                    ->orWhere('recipient_name', 'like', "%{$q}%")
                    ->orWhere('zong_sms_id', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        $logs = $logsQuery->paginate(20)->withQueryString();
        $gatewayConfig = $this->smsService->getConfig();
        $eventLabels = SmsTemplate::eventLabels();

        $stats = [
            'total_templates' => $templates->count(),
            'active_templates' => $templates->where('is_active', true)->count(),
            'total_sent' => SmsLog::where('status', 'sent')->count(),
            'total_simulated' => SmsLog::where('status', 'simulated')->count(),
            'total_failed' => SmsLog::where('status', 'failed')->count(),
        ];

        $applicants = Applicant::with([
            'district',
            'applications' => fn ($q) => $q->with('scheme')->latest('id'),
        ])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('full_name')
            ->get();

        $districts = District::orderBy('name')->get();
        $schemes = Scheme::orderBy('title')->get();
        $tehsils = $applicants->pluck('tehsil')
            ->filter(fn ($t) => ! empty(trim((string) $t)))
            ->map(fn ($t) => trim((string) $t))
            ->unique(fn ($t) => strtolower($t))
            ->sort()
            ->values();
        $operators = ZongSmsService::supportedOperators();

        return view('admin.sms-templates.index', compact(
            'templates',
            'logs',
            'gatewayConfig',
            'eventLabels',
            'stats',
            'applicants',
            'districts',
            'schemes',
            'tehsils',
            'operators'
        ));
    }

    public function create(Request $request): View
    {
        $template = new SmsTemplate([
            'event_key' => $request->string('event_key')->toString() ?: 'submitted',
            'send_language' => 'both',
            'is_active' => true,
            'message_en' => 'Dear {name}, your application {application_id} for {scheme} has been updated to status: {status}. Login with Application ID {application_id} and CNIC {password} at {tracking_url} - ALF Dept GB',
            'message_ur' => 'محترم {name}، آپ کی درخواست {application_id} برائے {scheme} کا اسٹیٹس اپڈیٹ ہو گیا ہے: {status}۔ محکمہ زراعت جی بی',
        ]);

        $schemes = Scheme::orderBy('title')->get();
        $eventLabels = SmsTemplate::eventLabels();
        $placeholders = SmsTemplate::availablePlaceholders();

        return view('admin.sms-templates.form', compact('template', 'schemes', 'eventLabels', 'placeholders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['scheme_id'] = $request->filled('scheme_id') ? (int) $request->input('scheme_id') : null;

        $template = SmsTemplate::create($data);

        return redirect()->route('admin.sms-templates.index')
            ->with('success', "SMS Template '{$template->name}' created successfully.");
    }

    public function edit(SmsTemplate $sms_template): View
    {
        $template = $sms_template;
        $schemes = Scheme::orderBy('title')->get();
        $eventLabels = SmsTemplate::eventLabels();
        $placeholders = SmsTemplate::availablePlaceholders();

        return view('admin.sms-templates.form', compact('template', 'schemes', 'eventLabels', 'placeholders'));
    }

    public function update(Request $request, SmsTemplate $sms_template): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['scheme_id'] = $request->filled('scheme_id') ? (int) $request->input('scheme_id') : null;

        $sms_template->update($data);

        return redirect()->route('admin.sms-templates.index')
            ->with('success', "SMS Template '{$sms_template->name}' updated successfully.");
    }

    public function destroy(SmsTemplate $sms_template): RedirectResponse
    {
        $name = $sms_template->name;
        $sms_template->delete();

        return redirect()->route('admin.sms-templates.index')
            ->with('success', "SMS Template '{$name}' deleted.");
    }

    public function toggle(SmsTemplate $sms_template): RedirectResponse
    {
        $sms_template->update([
            'is_active' => ! $sms_template->is_active,
        ]);

        $state = $sms_template->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "SMS Template '{$sms_template->name}' {$state}.");
    }

    public function updateGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_enabled' => ['nullable'],
            'zong_cbs_api_mode' => ['required', 'in:rest,rest_encoded,soap'],
            'zong_cbs_base_url' => ['required', 'url', 'max:255'],
            'zong_cbs_soap_url' => ['nullable', 'url', 'max:255'],
            'zong_cbs_login_id' => ['nullable', 'string', 'max:100'],
            'zong_cbs_login_password' => ['nullable', 'string', 'max:255'],
            'zong_cbs_mask' => ['required', 'string', 'max:50'],
            'zong_cbs_shortcode_preferred' => ['required', 'in:N,Y,n,y'],
        ]);

        SiteSetting::updateOrCreate(
            ['key' => 'sms_enabled'],
            ['value' => $request->boolean('sms_enabled') ? '1' : '0', 'label' => 'Enable Automated SMS Notifications', 'group' => 'sms']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'zong_cbs_api_mode'],
            ['value' => $data['zong_cbs_api_mode'], 'label' => 'Zong CBS API Protocol', 'group' => 'sms']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'zong_cbs_base_url'],
            ['value' => rtrim($data['zong_cbs_base_url'], '/'), 'label' => 'Zong CBS REST Base URL', 'group' => 'sms']
        );
        if (! empty($data['zong_cbs_soap_url'])) {
            SiteSetting::updateOrCreate(
                ['key' => 'zong_cbs_soap_url'],
                ['value' => rtrim($data['zong_cbs_soap_url'], '/'), 'label' => 'Zong CBS SOAP Endpoint URL', 'group' => 'sms']
            );
        }
        SiteSetting::updateOrCreate(
            ['key' => 'zong_cbs_login_id'],
            ['value' => trim((string) ($data['zong_cbs_login_id'] ?? '')), 'label' => 'Zong CBS Login ID', 'group' => 'sms']
        );
        if ($request->filled('zong_cbs_login_password')) {
            SiteSetting::updateOrCreate(
                ['key' => 'zong_cbs_login_password'],
                ['value' => $data['zong_cbs_login_password'], 'label' => 'Zong CBS Login Password', 'group' => 'sms']
            );
        }
        SiteSetting::updateOrCreate(
            ['key' => 'zong_cbs_mask'],
            ['value' => trim($data['zong_cbs_mask']), 'label' => 'Zong CBS Masking Name', 'group' => 'sms']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'zong_cbs_shortcode_preferred'],
            ['value' => strtoupper($data['zong_cbs_shortcode_preferred']), 'label' => 'Short Code Preferred', 'group' => 'sms']
        );

        return back()->with('success', 'Zong 4G CBS SMS Gateway configuration updated successfully.');
    }

    public function checkBalance(): RedirectResponse
    {
        $result = $this->smsService->getBalance();

        if ($result['success'] ?? false) {
            return back()->with(
                'success',
                "Zong CBS Balance Connected! Remaining SMS Count: {$result['total_sms_balance']} | Rate: {$result['rate']} | Expiry: {$result['expiry']}"
            );
        }

        return back()->with('error', 'Zong CBS Balance Check: ' . ($result['message'] ?? 'Unable to query balance.'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'language' => ['required', 'in:en,ur'],
            'message' => ['required', 'string', 'max:1500'],
        ]);

        $isUnicode = $data['language'] === 'ur' || ZongSmsService::requiresUnicode($data['message'], $data['language']);
        $log = $this->smsService->sendQuickSms($data['phone'], $data['message'], $isUnicode, [
            'event_key' => 'test_sms',
            'recipient_name' => 'Admin Test Recipient',
            'language' => $data['language'],
            'sent_by' => auth()->id(),
        ]);

        if ($log->status === 'sent') {
            return back()->with('success', "Test SMS sent successfully to {$log->recipient_phone} (Zong SMS ID: {$log->zong_sms_id}).");
        }

        if ($log->status === 'simulated') {
            return back()->with('success', "Test SMS logged in simulation mode for {$log->recipient_phone}. Configure Zong CBS Login ID & Password to dispatch live SMS.");
        }

        return back()->with('error', "Test SMS failed ({$log->zong_status_code}): " . SmsLog::zongCodeDescription($log->zong_status_code) . " — {$log->zong_response}");
    }

    public function sendBulk(Request $request): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Unauthorized. Only Super Admin is permitted to send bulk SMS.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'language' => ['nullable', 'in:auto,en,ur'],
            'select_all_applicants' => ['nullable', 'boolean'],
            'applicant_ids' => ['nullable', 'array'],
            'applicant_ids.*' => ['integer', 'exists:applicants,id'],
            'filter_district_id' => ['nullable', 'integer'],
            'filter_scheme_id' => ['nullable', 'integer'],
            'filter_tehsil' => ['nullable', 'string', 'max:120'],
            'filter_operator' => ['nullable', 'string', 'max:40'],
            'manual_numbers' => ['nullable', 'string', 'max:10000'],
        ]);

        $message = trim($data['message']);
        $langSetting = $data['language'] ?? 'auto';
        $recipients = [];

        // 1. Collect selected registered applicants (with optional District, Scheme, Tehsil & Mobile Operator filters)
        $applicantsQuery = Applicant::with(['district', 'applications' => fn ($q) => $q->with('scheme')->latest('id')])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('full_name');

        if (! empty($data['filter_district_id'])) {
            $applicantsQuery->where('district_id', (int) $data['filter_district_id']);
        }
        if (! empty(trim((string) ($data['filter_tehsil'] ?? '')))) {
            $applicantsQuery->whereRaw('LOWER(TRIM(tehsil)) = ?', [strtolower(trim((string) $data['filter_tehsil']))]);
        }
        if (! empty($data['filter_scheme_id'])) {
            $schemeId = (int) $data['filter_scheme_id'];
            $applicantsQuery->whereHas('applications', fn ($q) => $q->where('scheme_id', $schemeId));
        }

        if ($request->boolean('select_all_applicants')) {
            $selectedApplicants = $applicantsQuery->get();
        } elseif (! empty($data['applicant_ids'])) {
            $selectedApplicants = $applicantsQuery->whereIn('id', $data['applicant_ids'])->get();
        } else {
            $selectedApplicants = collect();
        }

        $filterOperator = strtolower(trim((string) ($data['filter_operator'] ?? '')));

        foreach ($selectedApplicants as $applicant) {
            $normalized = ZongSmsService::normalizePhone($applicant->phone);
            if ($normalized === '' || strlen($normalized) < 10) {
                continue;
            }
            $operator = ZongSmsService::detectOperator($normalized);
            if ($filterOperator !== '' && strtolower($operator) !== $filterOperator) {
                continue;
            }
            if (! isset($recipients[$normalized])) {
                $recipients[$normalized] = [
                    'name' => $applicant->full_name ?: 'Registered Applicant',
                    'cnic' => $applicant->cnic,
                    'source' => 'Registered Applicant',
                    'operator' => $operator,
                    'raw_phone' => $applicant->phone,
                    'normalized_phone' => $normalized,
                    'application_id' => $applicant->applications->first()?->id,
                ];
            }
        }

        // 2. Collect manually entered phone numbers (comma, semicolon, space, or newline separated)
        $manualRaw = trim((string) ($data['manual_numbers'] ?? ''));
        $invalidManual = [];
        if ($manualRaw !== '') {
            $tokens = preg_split('/[\s,;]+/', $manualRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $token) {
                $normalized = ZongSmsService::normalizePhone($token);
                if ($normalized === '' || strlen($normalized) < 10) {
                    $invalidManual[] = $token;
                    continue;
                }
                if (! isset($recipients[$normalized])) {
                    $recipients[$normalized] = [
                        'name' => 'Manual Number',
                        'cnic' => null,
                        'source' => 'Manual Entry',
                        'operator' => ZongSmsService::detectOperator($normalized),
                        'raw_phone' => $token,
                        'normalized_phone' => $normalized,
                        'application_id' => null,
                    ];
                }
            }
        }

        if (empty($recipients)) {
            return back()
                ->withInput()
                ->with('error', 'Please select at least one registered applicant or enter at least one valid mobile number.');
        }

        $forceUnicode = match ($langSetting) {
            'ur' => true,
            'en' => false,
            default => null,
        };
        $resolvedLang = match ($langSetting) {
            'ur' => 'ur',
            'en' => 'en',
            default => ZongSmsService::requiresUnicode($message) ? 'ur' : 'en',
        };

        $results = [];
        $counts = [
            'total' => 0,
            'sent' => 0,
            'simulated' => 0,
            'failed' => 0,
        ];

        foreach ($recipients as $recipient) {
            $log = $this->smsService->sendQuickSms(
                $recipient['normalized_phone'],
                $message,
                $forceUnicode,
                [
                    'application_id' => $recipient['application_id'],
                    'event_key' => 'bulk_admin',
                    'recipient_name' => $recipient['name'],
                    'language' => $resolvedLang,
                    'sent_by' => auth()->id(),
                ]
            );

            $counts['total']++;
            if (isset($counts[$log->status])) {
                $counts[$log->status]++;
            }

            $results[] = [
                'name' => $recipient['name'],
                'cnic' => $recipient['cnic'],
                'source' => $recipient['source'],
                'operator' => $recipient['operator'] ?? ZongSmsService::detectOperator($log->recipient_phone),
                'raw_phone' => $recipient['raw_phone'],
                'normalized_phone' => $log->recipient_phone,
                'status' => $log->status,
                'status_label' => match ($log->status) {
                    'sent' => 'Delivered / Sent',
                    'simulated' => 'Simulated',
                    default => 'Failed',
                },
                'zong_sms_id' => $log->zong_sms_id,
                'zong_status_code' => $log->zong_status_code,
                'zong_description' => SmsLog::zongCodeDescription($log->zong_status_code),
                'zong_response' => $log->zong_response,
                'message' => $log->message,
                'sent_at' => optional($log->sent_at ?? $log->created_at)->format('d M Y, h:i:s A'),
            ];
        }

        $summaryNote = "Bulk SMS processed for {$counts['total']} recipient(s): {$counts['sent']} delivered/sent, {$counts['simulated']} simulated, {$counts['failed']} failed.";
        if (! empty($invalidManual)) {
            $summaryNote .= ' Skipped invalid manual number(s): ' . implode(', ', $invalidManual) . '.';
        }

        $flashType = ($counts['sent'] > 0 || $counts['simulated'] > 0) ? 'success' : 'error';

        return redirect()
            ->route('admin.sms-templates.index')
            ->with($flashType, $summaryNote)
            ->with('bulk_sms_report', [
                'summary' => $counts,
                'message' => $message,
                'language' => $resolvedLang,
                'invalid_manual' => $invalidManual,
                'results' => $results,
            ]);
    }

    public function sendForApplication(Request $request, Application $application): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Unauthorized. Only Super Admin is permitted to manually send SMS notifications.');
        }

        $data = $request->validate([
            'event_key' => ['nullable', 'string', 'max:80'],
            'custom_message' => ['nullable', 'string', 'max:1500'],
            'language' => ['nullable', 'in:en,ur,both'],
        ]);

        $application->loadMissing(['applicant', 'scheme.department', 'district', 'bills']);
        $phone = $application->applicant?->phone;

        if (empty($phone)) {
            return back()->with('error', 'Applicant does not have a phone number on record.');
        }

        if (! empty(trim((string) ($data['custom_message'] ?? '')))) {
            $lang = $data['language'] ?? 'en';
            $vars = $this->smsService->buildTemplateVariables($application, $lang === 'ur' ? 'ur' : 'en');
            $interpolated = $this->smsService->interpolate($data['custom_message'], $vars);
            $log = $this->smsService->sendQuickSms($phone, $interpolated, null, [
                'application_id' => $application->id,
                'event_key' => 'manual_custom',
                'recipient_name' => $application->applicant?->full_name,
                'language' => $lang === 'ur' ? 'ur' : 'en',
                'sent_by' => auth()->id(),
            ]);

            return back()->with('success', "Custom SMS dispatched to {$log->recipient_phone} (Status: " . strtoupper($log->status) . ').');
        }

        $eventKey = $data['event_key'] ?: ($application->current_stage ?: 'submitted');
        $logs = $this->smsService->sendForApplicationEvent($application, $eventKey, [], auth()->id(), false);

        if (empty($logs)) {
            return back()->with('error', "No active SMS template found for event '{$eventKey}'. Enable or create a template in SMS Templates.");
        }

        return back()->with('success', count($logs) . " SMS notification(s) dispatched to {$application->applicant?->full_name} ({$phone}).");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_key' => ['required', 'string', 'max:80'],
            'scheme_id' => ['nullable', 'exists:schemes,id'],
            'send_language' => ['required', 'in:en,ur,both'],
            'message_en' => ['nullable', 'required_if:send_language,en,both', 'string', 'max:1920'],
            'message_ur' => ['nullable', 'required_if:send_language,ur,both', 'string', 'max:770'],
        ]);
    }
}


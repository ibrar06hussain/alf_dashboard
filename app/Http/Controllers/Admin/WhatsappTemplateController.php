<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SiteSetting;
use App\Models\WhatsappLog;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappService;
use App\Services\ZongSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappTemplateController extends Controller
{
    public function __construct(
        private readonly WhatsappService $whatsappService,
        private readonly ZongSmsService $smsService
    ) {}

    public function index(Request $request): View
    {
        $templates = WhatsappTemplate::with('scheme')
            ->withCount('logs')
            ->orderBy('id')
            ->get();

        $logsQuery = WhatsappLog::with(['application', 'template', 'sender'])->latest();

        if ($request->filled('event_key')) {
            $logsQuery->where('event_key', $request->string('event_key'));
        }
        if ($request->filled('status')) {
            $logsQuery->where('status', $request->string('status'));
        }
        if ($request->filled('operator')) {
            $logsQuery->where('operator', $request->string('operator'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $logsQuery->where(function ($sub) use ($q) {
                $sub->where('recipient_phone', 'like', "%{$q}%")
                    ->orWhere('recipient_name', 'like', "%{$q}%")
                    ->orWhere('provider_message_id', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        $logs = $logsQuery->paginate(20)->withQueryString();
        $gatewayConfig = $this->whatsappService->getConfig();
        $eventLabels = WhatsappTemplate::eventLabels();

        $stats = [
            'total_templates' => $templates->count(),
            'active_templates' => $templates->where('is_active', true)->count(),
            'total_sent' => WhatsappLog::where('status', 'sent')->count(),
            'total_simulated' => WhatsappLog::where('status', 'simulated')->count(),
            'total_failed' => WhatsappLog::where('status', 'failed')->count(),
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
        $operators = WhatsappService::supportedOperators();

        return view('admin.whatsapp-templates.index', compact(
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
        $template = new WhatsappTemplate([
            'event_key' => $request->string('event_key')->toString() ?: 'submitted',
            'send_language' => 'both',
            'is_active' => true,
            'message_en' => "*ALF Department Gilgit-Baltistan*\nDear *{name}*, your application *{application_id}* for *{scheme}* has been updated to status: *{status}*.\nTrack status at {tracking_url} with Application ID *{application_id}* and CNIC *{password}*.",
            'message_ur' => "*محکمہ زراعت گلگت بلتستان*\nمحترم *{name}*، آپ کی درخواست *{application_id}* برائے *{scheme}* کا اسٹیٹس اپڈیٹ ہو گیا ہے: *{status}*۔",
        ]);

        $schemes = Scheme::orderBy('title')->get();
        $eventLabels = WhatsappTemplate::eventLabels();
        $placeholders = WhatsappTemplate::availablePlaceholders();

        return view('admin.whatsapp-templates.form', compact('template', 'schemes', 'eventLabels', 'placeholders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['scheme_id'] = $request->filled('scheme_id') ? (int) $request->input('scheme_id') : null;

        $template = WhatsappTemplate::create($data);

        return redirect()->route('admin.whatsapp-templates.index')
            ->with('success', "WhatsApp Template '{$template->name}' created successfully.");
    }

    public function edit(WhatsappTemplate $whatsapp_template): View
    {
        $template = $whatsapp_template;
        $schemes = Scheme::orderBy('title')->get();
        $eventLabels = WhatsappTemplate::eventLabels();
        $placeholders = WhatsappTemplate::availablePlaceholders();

        return view('admin.whatsapp-templates.form', compact('template', 'schemes', 'eventLabels', 'placeholders'));
    }

    public function update(Request $request, WhatsappTemplate $whatsapp_template): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['scheme_id'] = $request->filled('scheme_id') ? (int) $request->input('scheme_id') : null;

        $whatsapp_template->update($data);

        return redirect()->route('admin.whatsapp-templates.index')
            ->with('success', "WhatsApp Template '{$whatsapp_template->name}' updated successfully.");
    }

    public function destroy(WhatsappTemplate $whatsapp_template): RedirectResponse
    {
        $name = $whatsapp_template->name;
        $whatsapp_template->delete();

        return redirect()->route('admin.whatsapp-templates.index')
            ->with('success', "WhatsApp Template '{$name}' deleted.");
    }

    public function toggle(WhatsappTemplate $whatsapp_template): RedirectResponse
    {
        $whatsapp_template->update([
            'is_active' => ! $whatsapp_template->is_active,
        ]);

        $state = $whatsapp_template->is_active ? 'enabled' : 'disabled';

        return back()->with('success', "WhatsApp Template '{$whatsapp_template->name}' {$state}.");
    }

    public function updateGateway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_enabled' => ['nullable'],
            'whatsapp_provider' => ['required', 'in:meta_cloud,ultramsg,custom_http'],
            'whatsapp_api_url' => ['required', 'url', 'max:255'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_business_account_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_sender_number' => ['nullable', 'string', 'max:50'],
            'whatsapp_verify_token' => ['nullable', 'string', 'max:255'],
        ]);

        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_enabled'],
            ['value' => $request->boolean('whatsapp_enabled') ? '1' : '0', 'label' => 'Enable Automated WhatsApp Notifications', 'group' => 'whatsapp']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_provider'],
            ['value' => $data['whatsapp_provider'], 'label' => 'WhatsApp API Provider', 'group' => 'whatsapp']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_api_url'],
            ['value' => rtrim($data['whatsapp_api_url'], '/'), 'label' => 'WhatsApp API Base URL', 'group' => 'whatsapp']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_phone_number_id'],
            ['value' => trim((string) ($data['whatsapp_phone_number_id'] ?? '')), 'label' => 'WhatsApp Phone Number ID / Instance ID', 'group' => 'whatsapp']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_business_account_id'],
            ['value' => trim((string) ($data['whatsapp_business_account_id'] ?? '')), 'label' => 'WhatsApp Business Account ID', 'group' => 'whatsapp']
        );
        if ($request->filled('whatsapp_access_token')) {
            SiteSetting::updateOrCreate(
                ['key' => 'whatsapp_access_token'],
                ['value' => trim($data['whatsapp_access_token']), 'label' => 'WhatsApp API Access Token / API Key', 'group' => 'whatsapp']
            );
        }
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_sender_number'],
            ['value' => trim((string) ($data['whatsapp_sender_number'] ?? '')), 'label' => 'WhatsApp Sender / Display Number', 'group' => 'whatsapp']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'whatsapp_verify_token'],
            ['value' => trim((string) ($data['whatsapp_verify_token'] ?? '')), 'label' => 'WhatsApp Webhook Verify Token', 'group' => 'whatsapp']
        );

        return back()->with('success', 'WhatsApp Business API Gateway configuration updated successfully.');
    }

    public function checkConnection(): RedirectResponse
    {
        $result = $this->whatsappService->checkConnection();

        if ($result['success'] ?? false) {
            return back()->with('success', 'WhatsApp API Connected! ' . ($result['message'] ?? ''));
        }

        return back()->with('error', 'WhatsApp API Connection Check: ' . ($result['message'] ?? 'Unable to verify connection.'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'language' => ['required', 'in:en,ur'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $log = $this->whatsappService->sendMessage($data['phone'], $data['message'], [
            'event_key' => 'test_whatsapp',
            'recipient_name' => 'Admin Test Recipient',
            'language' => $data['language'],
            'sent_by' => auth()->id(),
        ]);

        if ($log->status === 'sent') {
            return back()->with('success', "Test WhatsApp message sent successfully to {$log->recipient_phone} (Message ID: {$log->provider_message_id}).");
        }

        if ($log->status === 'simulated') {
            return back()->with('success', "Test WhatsApp message logged in simulation mode for {$log->recipient_phone}. Configure WhatsApp Access Token & Phone Number ID to dispatch live messages.");
        }

        return back()->with('error', "Test WhatsApp failed ({$log->provider_status_code}): " . WhatsappLog::statusDescription($log->provider_status_code) . " — {$log->provider_response}");
    }

    public function sendBulk(Request $request): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Unauthorized. Only Super Admin is permitted to send bulk WhatsApp messages.');
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4096'],
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
            $normalized = WhatsappService::normalizePhone($applicant->phone);
            if ($normalized === '' || strlen($normalized) < 10) {
                continue;
            }
            $operator = WhatsappService::detectOperator($normalized);
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

        // 2. Collect manually entered phone numbers
        $manualRaw = trim((string) ($data['manual_numbers'] ?? ''));
        $invalidManual = [];
        if ($manualRaw !== '') {
            $tokens = preg_split('/[\s,;]+/', $manualRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($tokens as $token) {
                $normalized = WhatsappService::normalizePhone($token);
                if ($normalized === '' || strlen($normalized) < 10) {
                    $invalidManual[] = $token;
                    continue;
                }
                if (! isset($recipients[$normalized])) {
                    $recipients[$normalized] = [
                        'name' => 'Manual Number',
                        'cnic' => null,
                        'source' => 'Manual Entry',
                        'operator' => WhatsappService::detectOperator($normalized),
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
            $log = $this->whatsappService->sendMessage(
                $recipient['normalized_phone'],
                $message,
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
                'operator' => $recipient['operator'] ?? $log->operator,
                'raw_phone' => $recipient['raw_phone'],
                'normalized_phone' => $log->recipient_phone,
                'status' => $log->status,
                'status_label' => match ($log->status) {
                    'sent' => 'Delivered / Sent',
                    'simulated' => 'Simulated',
                    default => 'Failed',
                },
                'provider_message_id' => $log->provider_message_id,
                'provider_status_code' => $log->provider_status_code,
                'provider_description' => WhatsappLog::statusDescription($log->provider_status_code),
                'provider_response' => $log->provider_response,
                'message' => $log->message,
                'sent_at' => optional($log->sent_at ?? $log->created_at)->format('d M Y, h:i:s A'),
            ];
        }

        $summaryNote = "Bulk WhatsApp processed for {$counts['total']} recipient(s): {$counts['sent']} delivered/sent, {$counts['simulated']} simulated, {$counts['failed']} failed.";
        if (! empty($invalidManual)) {
            $summaryNote .= ' Skipped invalid manual number(s): ' . implode(', ', $invalidManual) . '.';
        }

        $flashType = ($counts['sent'] > 0 || $counts['simulated'] > 0) ? 'success' : 'error';

        return redirect()
            ->route('admin.whatsapp-templates.index')
            ->with($flashType, $summaryNote)
            ->with('bulk_whatsapp_report', [
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
            abort(403, 'Unauthorized. Only Super Admin is permitted to manually send WhatsApp notifications.');
        }

        $data = $request->validate([
            'event_key' => ['nullable', 'string', 'max:80'],
            'custom_message' => ['nullable', 'string', 'max:4096'],
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
            $log = $this->whatsappService->sendMessage($phone, $interpolated, [
                'application_id' => $application->id,
                'event_key' => 'manual_custom',
                'recipient_name' => $application->applicant?->full_name,
                'language' => $lang === 'ur' ? 'ur' : 'en',
                'sent_by' => auth()->id(),
            ]);

            return back()->with('success', "Custom WhatsApp message dispatched to {$log->recipient_phone} (Status: " . strtoupper($log->status) . ').');
        }

        $eventKey = $data['event_key'] ?: ($application->current_stage ?: 'submitted');
        $logs = $this->whatsappService->sendForApplicationEvent($application, $eventKey, [], auth()->id());

        if (empty($logs)) {
            return back()->with('error', "No active WhatsApp template found for event '{$eventKey}'. Enable or create a template in WhatsApp Templates.");
        }

        return back()->with('success', count($logs) . " WhatsApp notification(s) dispatched to {$application->applicant?->full_name} ({$phone}).");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_key' => ['required', 'string', 'max:80'],
            'scheme_id' => ['nullable', 'exists:schemes,id'],
            'send_language' => ['required', 'in:en,ur,both'],
            'template_name' => ['nullable', 'string', 'max:120'],
            'message_en' => ['nullable', 'required_if:send_language,en,both', 'string', 'max:4096'],
            'message_ur' => ['nullable', 'required_if:send_language,ur,both', 'string', 'max:4096'],
        ]);
    }
}


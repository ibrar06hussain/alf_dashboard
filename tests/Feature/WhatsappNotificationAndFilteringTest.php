<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\WhatsappLog;
use App\Models\WhatsappTemplate;
use App\Services\ApplicationLifecycleService;
use App\Services\ZongSmsService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappNotificationAndFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $ddGilgit;
    protected District $gilgit;
    protected District $skardu;
    protected Scheme $wheatScheme;
    protected Scheme $potatoScheme;
    protected Applicant $zongApplicantGilgit;
    protected Applicant $telenorApplicantGilgit;
    protected Applicant $zongApplicantSkardu;
    protected Applicant $scomApplicantGilgit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);
        $this->skardu = District::firstOrCreate(['id' => 2], ['name' => 'Skardu', 'code' => 'SKD']);

        $this->superAdmin = User::factory()->create([
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->ddGilgit = User::factory()->create([
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->ddGilgit->assignRole('District Deputy Director');

        $this->wheatScheme = Scheme::firstOrCreate(
            ['slug' => 'wheat-seed-wa-test'],
            [
                'title' => 'Wheat Seed Scheme',
                'status' => 'active',
                'department_id' => 1,
                'requires_challan' => true,
                'challan_fee_per_unit' => 3000.00,
            ]
        );

        $this->potatoScheme = Scheme::firstOrCreate(
            ['slug' => 'potato-seed-wa-test'],
            [
                'title' => 'Potato Seed Scheme',
                'status' => 'active',
                'department_id' => 1,
                'requires_challan' => false,
            ]
        );

        // 1. Zong user in Gilgit (Danyore tehsil, Wheat scheme)
        $this->zongApplicantGilgit = Applicant::create([
            'cnic' => '7110111111111',
            'full_name' => 'Ali Zong Gilgit',
            'father_name' => 'Karim',
            'phone' => '0312-5551111',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Danyore',
        ]);
        Application::create([
            'application_no' => 'ALF-WA-0001',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $this->zongApplicantGilgit->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // 2. Telenor user in Gilgit (Gilgit tehsil, Wheat scheme)
        $this->telenorApplicantGilgit = Applicant::create([
            'cnic' => '7110122222222',
            'full_name' => 'Bilal Telenor Gilgit',
            'father_name' => 'Hassan',
            'phone' => '0345-5552222',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
        ]);
        Application::create([
            'application_no' => 'ALF-WA-0002',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $this->telenorApplicantGilgit->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // 3. Zong user in Skardu (Skardu tehsil, Potato scheme)
        $this->zongApplicantSkardu = Applicant::create([
            'cnic' => '7150133333333',
            'full_name' => 'Comran Zong Skardu',
            'father_name' => 'Abbas',
            'phone' => '0315-5553333',
            'district_id' => $this->skardu->id,
            'tehsil' => 'Skardu',
        ]);
        Application::create([
            'application_no' => 'ALF-WA-0003',
            'scheme_id' => $this->potatoScheme->id,
            'applicant_id' => $this->zongApplicantSkardu->id,
            'district_id' => $this->skardu->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // 4. SCOM user in Gilgit (Danyore tehsil, Potato scheme)
        $this->scomApplicantGilgit = Applicant::create([
            'cnic' => '7110144444444',
            'full_name' => 'Dawood SCOM Gilgit',
            'father_name' => 'Hussain',
            'phone' => '0355-5554444',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Danyore',
        ]);
        Application::create([
            'application_no' => 'ALF-WA-0004',
            'scheme_id' => $this->potatoScheme->id,
            'applicant_id' => $this->scomApplicantGilgit->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    private function configureLiveWhatsappGateway(): void
    {
        SiteSetting::updateOrCreate(['key' => 'whatsapp_enabled'], ['value' => '1', 'group' => 'whatsapp']);
        SiteSetting::updateOrCreate(['key' => 'whatsapp_provider'], ['value' => 'meta_cloud', 'group' => 'whatsapp']);
        SiteSetting::updateOrCreate(['key' => 'whatsapp_api_url'], ['value' => 'https://graph.facebook.com/v20.0', 'group' => 'whatsapp']);
        SiteSetting::updateOrCreate(['key' => 'whatsapp_phone_number_id'], ['value' => '109988776655', 'group' => 'whatsapp']);
        SiteSetting::updateOrCreate(['key' => 'whatsapp_access_token'], ['value' => 'EAAGTestToken123456', 'group' => 'whatsapp']);
    }

    private function configureLiveSmsGateway(): void
    {
        SiteSetting::updateOrCreate(['key' => 'sms_enabled'], ['value' => '1', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_api_mode'], ['value' => 'rest', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_login_id'], ['value' => '923115960959', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_login_password'], ['value' => 'SecretPass123', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_mask'], ['value' => 'ALF-GB', 'group' => 'sms']);
    }

    public function test_mobile_operator_detection_identifies_pakistani_networks(): void
    {
        $this->assertSame('Zong', ZongSmsService::detectOperator('0312-5551111'));
        $this->assertSame('Zong', ZongSmsService::detectOperator('923155553333'));
        $this->assertSame('Zong', ZongSmsService::detectOperator('0370-1234567'));
        $this->assertSame('Jazz', ZongSmsService::detectOperator('0300-1234567'));
        $this->assertSame('Jazz', ZongSmsService::detectOperator('0321-1234567'));
        $this->assertSame('Ufone', ZongSmsService::detectOperator('0333-1234567'));
        $this->assertSame('Telenor', ZongSmsService::detectOperator('0345-5552222'));
        $this->assertSame('SCOM', ZongSmsService::detectOperator('0355-5554444'));
    }

    public function test_whatsapp_gateway_screen_is_super_admin_only_and_displays_filters_and_templates(): void
    {
        $this->actingAs($this->ddGilgit)
            ->get(route('admin.whatsapp-templates.index'))
            ->assertForbidden();

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.whatsapp-templates.index'));

        $response->assertOk()
            ->assertSee('WhatsApp Notifications & Business API Gateway')
            ->assertSee('Send Bulk WhatsApp Message to Registered Applicants &amp; Manual Numbers', false)
            ->assertSee('All Districts')
            ->assertSee('All Tehsils')
            ->assertSee('All Schemes')
            ->assertSee('All Operators')
            ->assertSee('Zong (0310–0319 / 0370)')
            ->assertSee('Ali Zong Gilgit')
            ->assertSee('Comran Zong Skardu');
    }

    public function test_super_admin_can_send_bulk_whatsapp_filtered_by_zong_operator_only(): void
    {
        $this->configureLiveWhatsappGateway();

        Http::fake([
            'graph.facebook.com/v20.0/109988776655/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.HBgM923125551111']],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->post(route('admin.whatsapp-templates.send-bulk'), [
                'message' => 'Special notice for Zong subscribers only.',
                'language' => 'en',
                'select_all_applicants' => '1',
                'filter_operator' => 'Zong',
            ]);

        $response->assertOk()
            ->assertSee('Bulk WhatsApp Delivery Report')
            ->assertSee('Total: 2')
            ->assertSee('Delivered / Sent: 2')
            ->assertSee('Ali Zong Gilgit')
            ->assertSee('Comran Zong Skardu')
            ->assertSee('wamid.HBgM923125551111');

        $this->assertDatabaseCount('whatsapp_logs', 2);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '923125551111',
            'operator' => 'Zong',
            'status' => 'sent',
            'provider_message_id' => 'wamid.HBgM923125551111',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '923155553333',
            'operator' => 'Zong',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('whatsapp_logs', [
            'recipient_phone' => '923455552222', // Telenor excluded
        ]);
        $this->assertDatabaseMissing('whatsapp_logs', [
            'recipient_phone' => '923555554444', // SCOM excluded
        ]);
    }

    public function test_super_admin_can_filter_bulk_sms_by_district_tehsil_scheme_and_operator(): void
    {
        $this->configureLiveSmsGateway();

        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220987777', 200),
        ]);

        // Filter: District = Gilgit, Tehsil = Danyore, Scheme = Wheat Seed, Operator = Zong
        // Only Ali Zong Gilgit matches all four criteria!
        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->post(route('admin.sms-templates.send-bulk'), [
                'message' => 'Targeted SMS for Danyore Wheat Zong applicants.',
                'language' => 'en',
                'select_all_applicants' => '1',
                'filter_district_id' => $this->gilgit->id,
                'filter_tehsil' => 'Danyore',
                'filter_scheme_id' => $this->wheatScheme->id,
                'filter_operator' => 'Zong',
            ]);

        $response->assertOk()
            ->assertSee('Bulk SMS Delivery Report')
            ->assertSee('Total: 1')
            ->assertSee('Ali Zong Gilgit')
            ->assertSee('923125551111');

        $this->assertDatabaseCount('sms_logs', 1);
        $this->assertDatabaseHas('sms_logs', [
            'recipient_phone' => '923125551111',
            'recipient_name' => 'Ali Zong Gilgit',
            'status' => 'sent',
        ]);
    }

    public function test_super_admin_can_create_edit_and_trigger_whatsapp_template_on_application_lifecycle(): void
    {
        $this->configureLiveWhatsappGateway();

        Http::fake([
            'graph.facebook.com/v20.0/109988776655/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.LIFECYCLE123']],
            ], 200),
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220988888', 200),
        ]);

        // Create a custom WhatsApp template for 'under_review'
        $createRes = $this->actingAs($this->superAdmin)
            ->post(route('admin.whatsapp-templates.store'), [
                'name' => 'Under Review WhatsApp Alert',
                'event_key' => Application::STAGE_UNDER_REVIEW,
                'scheme_id' => $this->wheatScheme->id,
                'send_language' => 'en',
                'template_name' => 'under_review_alert',
                'message_en' => 'Dear *{name}*, your application *{application_id}* is now under review in {district}.',
                'is_active' => '1',
            ]);

        $createRes->assertRedirect(route('admin.whatsapp-templates.index'));
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'Under Review WhatsApp Alert',
            'event_key' => Application::STAGE_UNDER_REVIEW,
        ]);

        // Trigger lifecycle transition to under_review
        $app = Application::where('application_no', 'ALF-WA-0001')->firstOrFail();
        app(ApplicationLifecycleService::class)->transition(
            $app,
            Application::STAGE_UNDER_REVIEW,
            $this->ddGilgit,
            ['note' => 'Moved to review']
        );

        $waLog = WhatsappLog::where('application_id', $app->id)
            ->where('event_key', Application::STAGE_UNDER_REVIEW)
            ->first();

        $this->assertNotNull($waLog);
        $this->assertSame('sent', $waLog->status);
        $this->assertSame('wamid.LIFECYCLE123', $waLog->provider_message_id);
        $this->assertStringContainsString('ALF-WA-0001', $waLog->message);
    }
}


<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeStockItem;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\ApplicationLifecycleService;
use App\Services\GbpayService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $ddGilgit;
    protected District $gilgit;
    protected Scheme $scheme;
    protected Applicant $applicant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);

        $this->scheme = Scheme::firstOrCreate(
            ['id' => 1],
            [
                'title' => 'Wheat Seed Distribution Scheme',
                'slug' => 'wheat-seed-sms-test',
                'status' => 'active',
                'department_id' => 1,
                'requires_challan' => true,
                'challan_fee_per_unit' => 3000.00,
            ]
        );

        SchemeStockItem::create([
            'scheme_id' => $this->scheme->id,
            'item_name' => 'Certified Wheat Seed Bag (50kg)',
            'unit' => 'Bags',
            'is_active' => true,
        ]);

        $this->superAdmin = User::firstOrCreate(
            ['email' => 'superadmin.sms.test@alf.gb.gov.pk'],
            [
                'name' => 'Super Admin SMS',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
        $this->superAdmin->syncRoles(['Super Admin']);

        $this->ddGilgit = User::firstOrCreate(
            ['email' => 'dd.gilgit.sms.test@alf.gb.gov.pk'],
            [
                'name' => 'Deputy Director Gilgit',
                'password' => bcrypt('password123'),
                'district_id' => $this->gilgit->id,
                'department_id' => 1,
                'is_active' => true,
            ]
        );
        $this->ddGilgit->syncRoles(['District Deputy Director']);

        $this->applicant = Applicant::create([
            'full_name' => 'Muhammad Ali Khan',
            'father_name' => 'Ahmed Khan',
            'cnic' => '7110112345671',
            'phone' => '03455123456',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'village' => 'Jutial',
        ]);

        // Configure Zong CBS Gateway Settings
        SiteSetting::setValue('zong_cbs_enabled', '1', 'sms');
        SiteSetting::setValue('zong_cbs_base_url', 'https://cbs.zong.com.pk/reachrestapi/home', 'sms');
        SiteSetting::setValue('zong_cbs_login_id', '923149059535', 'sms');
        SiteSetting::setValue('zong_cbs_login_password', 'ZongTestPass123', 'sms');
        SiteSetting::setValue('zong_cbs_mask', 'ALFFD-GB', 'sms');
        SiteSetting::setValue('zong_cbs_shortcode_preferred', 'N', 'sms');
    }

    public function test_registration_submission_sends_sms_with_application_id_and_cnic_password(): void
    {
        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980151', 200),
        ]);

        $app = Application::create([
            'application_no' => 'ALF-2026-000101',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $this->applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $lifecycle = app(ApplicationLifecycleService::class);
        $lifecycle->onSubmission($app);

        $logs = SmsLog::where('application_id', $app->id)
            ->where('event_key', SmsTemplate::EVENT_SUBMITTED)
            ->orderBy('language')
            ->get();

        // Both English (en) and Urdu (ur) SMS messages are sent by default ('both')
        $this->assertCount(2, $logs);

        $enLog = $logs->firstWhere('language', 'en');
        $urLog = $logs->firstWhere('language', 'ur');

        $this->assertNotNull($enLog);
        $this->assertEquals('923455123456', $enLog->recipient_phone);
        $this->assertFalse($enLog->is_unicode);
        $this->assertEquals('sent', $enLog->status);
        $this->assertEquals('5220980151', $enLog->zong_sms_id);
        $this->assertStringContainsString('ALF-2026-000101', $enLog->message);
        $this->assertStringContainsString('7110112345671', $enLog->message); // CNIC as password

        $this->assertNotNull($urLog);
        $this->assertTrue($urLog->is_unicode);
        $this->assertEquals('sent', $urLog->status);
        $this->assertStringContainsString('ALF-2026-000101', $urLog->message);
        $this->assertStringContainsString('7110112345671', $urLog->message);

        // Verify Zong CBS API payload structure
        Http::assertSent(function ($request) {
            $data = $request->data();
            return str_contains($request->url(), '/SendQuickSMS')
                && ($data['loginId'] ?? '') === '923149059535'
                && ($data['Destination'] ?? '') === '923455123456'
                && ($data['Mask'] ?? '') === 'ALFFD-GB'
                && in_array($data['UniCode'] ?? '', ['0', '1'], true);
        });
    }

    public function test_approval_sends_sms_with_approved_bags_challan_amount_and_psid(): void
    {
        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980222', 200),
        ]);

        $app = Application::create([
            'application_no' => 'ALF-2026-000102',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $this->applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => Application::STAGE_UNDER_REVIEW,
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'submitted_at' => now(),
        ]);

        $lifecycle = app(ApplicationLifecycleService::class);
        $updated = $lifecycle->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
            'approved_quantity' => 4,
            'note' => 'Approved 4 bags after land verification.',
        ]);

        // Approval auto-issues challan and triggers the 'approved' SMS notification
        $logs = SmsLog::where('application_id', $updated->id)
            ->where('event_key', SmsTemplate::EVENT_APPROVED)
            ->get();

        $this->assertCount(2, $logs);

        $enLog = $logs->firstWhere('language', 'en');
        $urLog = $logs->firstWhere('language', 'ur');

        $this->assertStringContainsString('ALF-2026-000102', $enLog->message);
        $this->assertStringContainsString('4', $enLog->message); // 4 approved bags
        $this->assertStringContainsString(number_format((float) $updated->challan_amount, 0), $enLog->message);
        if ($updated->psid) {
            $this->assertStringContainsString($updated->psid, $enLog->message);
        }

        $this->assertStringContainsString('4', $urLog->message);
        $this->assertTrue($urLog->is_unicode);
    }

    public function test_rejection_sends_sms_with_application_id_and_rejection_reason(): void
    {
        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980333', 200),
        ]);

        $app = Application::create([
            'application_no' => 'ALF-2026-000103',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $this->applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => Application::STAGE_UNDER_REVIEW,
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'submitted_at' => now(),
        ]);

        $lifecycle = app(ApplicationLifecycleService::class);
        $lifecycle->transition($app, Application::STAGE_REJECTED, $this->ddGilgit, [
            'rejection_reason' => 'Insufficient cultivable land verified during survey',
        ]);

        $logs = SmsLog::where('application_id', $app->id)
            ->where('event_key', SmsTemplate::EVENT_REJECTED)
            ->get();

        $this->assertCount(2, $logs);

        $enLog = $logs->firstWhere('language', 'en');
        $urLog = $logs->firstWhere('language', 'ur');

        $this->assertStringContainsString('ALF-2026-000103', $enLog->message);
        $this->assertStringContainsString('Insufficient cultivable land verified during survey', $enLog->message);
        $this->assertStringContainsString('ALF-2026-000103', $urLog->message);
    }

    public function test_gbpay_bill_payment_success_sends_sms_with_bill_paid_amount_and_psid(): void
    {
        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980444', 200),
        ]);

        $app = Application::create([
            'application_no' => 'ALF-2026-000104',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $this->applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => Application::STAGE_UNDER_REVIEW,
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'submitted_at' => now(),
        ]);

        $lifecycle = app(ApplicationLifecycleService::class);
        $app = $lifecycle->transition($app, Application::STAGE_APPROVED, $this->ddGilgit, [
            'approved_quantity' => 3,
        ]);

        $gbpay = app(GbpayService::class);
        $bill = $app->activeBill ?? $gbpay->createBillForApplication($app)['bill'];
        $this->assertNotNull($bill);

        // Simulate GBPay Bill Payment success
        $gbpay->markBillAsPaid(
            $bill,
            'GBPAY889900',
            (float) $bill->bill_amount,
            'KCBL'
        );

        $logs = SmsLog::where('application_id', $app->id)
            ->where('event_key', SmsTemplate::EVENT_GBPAY_PAYMENT_SUCCESS)
            ->get();

        $this->assertCount(2, $logs);

        $enLog = $logs->firstWhere('language', 'en');
        $urLog = $logs->firstWhere('language', 'ur');

        $expectedAmountFormatted = number_format((float) $bill->bill_amount, 0);

        $this->assertStringContainsString($expectedAmountFormatted, $enLog->message);
        $this->assertStringContainsString($bill->psid, $enLog->message);
        $this->assertStringContainsString('GBPAY889900', $enLog->message);
        $this->assertStringContainsString('ALF-2026-000104', $enLog->message);

        $this->assertStringContainsString($expectedAmountFormatted, $urLog->message);
        $this->assertStringContainsString($bill->psid, $urLog->message);
    }

    public function test_admin_can_configure_sms_template_for_any_other_status_and_it_triggers_automatically(): void
    {
        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980555', 200),
        ]);

        // Admin creates a template for 'under_review' status via Admin Controller
        $response = $this->actingAs($this->superAdmin)->post(route('admin.sms-templates.store'), [
            'name' => 'Application Under Desk Review Alert',
            'event_key' => Application::STAGE_UNDER_REVIEW,
            'scheme_id' => $this->scheme->id,
            'send_language' => 'both',
            'message_en' => 'Dear {name} (CNIC: {cnic}), your application {application_id} for {scheme} is now {status} in {district}.',
            'message_ur' => 'محترم {name} (شناختی کارڈ: {cnic})، آپ کی درخواست {application_id} اس وقت زیرِ جائزہ ہے۔',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.sms-templates.index'));

        $template = SmsTemplate::where('event_key', Application::STAGE_UNDER_REVIEW)
            ->where('scheme_id', $this->scheme->id)
            ->first();
        $this->assertNotNull($template);

        $app = Application::create([
            'application_no' => 'ALF-2026-000105',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $this->applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => 1,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Transition application to 'under_review'
        $lifecycle = app(ApplicationLifecycleService::class);
        $lifecycle->transition($app, Application::STAGE_UNDER_REVIEW, $this->ddGilgit, [
            'note' => 'Moved to desk review.',
        ]);

        $logs = SmsLog::where('application_id', $app->id)
            ->where('event_key', Application::STAGE_UNDER_REVIEW)
            ->get();

        $this->assertCount(2, $logs);

        $enLog = $logs->firstWhere('language', 'en');
        $urLog = $logs->firstWhere('language', 'ur');

        $this->assertStringContainsString('Muhammad Ali Khan', $enLog->message);
        $this->assertStringContainsString('71101-1234567-1', $enLog->message);
        $this->assertStringContainsString('ALF-2026-000105', $enLog->message);
        $this->assertStringContainsString('Wheat Seed Distribution Scheme', $enLog->message);
        $this->assertStringContainsString('Gilgit', $enLog->message);

        $this->assertStringContainsString('Muhammad Ali Khan', $urLog->message);
        $this->assertStringContainsString('71101-1234567-1', $urLog->message);
        $this->assertStringContainsString('ALF-2026-000105', $urLog->message);
    }

    public function test_admin_can_edit_existing_sms_template_and_preview_interpolation(): void
    {
        $submittedTemplate = SmsTemplate::where('event_key', SmsTemplate::EVENT_SUBMITTED)->firstOrFail();

        $response = $this->actingAs($this->superAdmin)->put(route('admin.sms-templates.update', $submittedTemplate), [
            'name' => 'Updated Registration SMS',
            'event_key' => SmsTemplate::EVENT_SUBMITTED,
            'scheme_id' => null,
            'send_language' => 'en',
            'message_en' => 'Welcome {name}! App ID: {application_id}, Login ID: {cnic}, Password: {password}.',
            'message_ur' => $submittedTemplate->message_ur,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.sms-templates.index'));

        $submittedTemplate->refresh();
        $this->assertEquals('en', $submittedTemplate->send_language);
        $this->assertStringContainsString('Login ID: {cnic}, Password: {password}', $submittedTemplate->message_en);
    }
}


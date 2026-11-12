<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\District;
use App\Models\SiteSetting;
use App\Models\SmsLog;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BulkApplicantSmsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $ddGilgit;
    protected District $gilgit;
    protected Applicant $applicantOne;
    protected Applicant $applicantTwo;
    protected Applicant $applicantThree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT']);

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

        $this->applicantOne = Applicant::create([
            'cnic' => '7110112345671',
            'full_name' => 'Karim Ullah',
            'father_name' => 'Aman Ullah',
            'phone' => '0345-8161101',
            'district_id' => $this->gilgit->id,
        ]);

        $this->applicantTwo = Applicant::create([
            'cnic' => '7110112345672',
            'full_name' => 'Zainab Bibi',
            'father_name' => 'Ali Khan',
            'phone' => '0345-8161102',
            'district_id' => $this->gilgit->id,
        ]);

        $this->applicantThree = Applicant::create([
            'cnic' => '7110112345673',
            'full_name' => 'Hassan Raza',
            'father_name' => 'Ghulam Raza',
            'phone' => '0345-8161103',
            'district_id' => $this->gilgit->id,
        ]);
    }

    private function configureLiveGateway(): void
    {
        SiteSetting::updateOrCreate(['key' => 'sms_enabled'], ['value' => '1', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_api_mode'], ['value' => 'rest', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_login_id'], ['value' => '923115960959', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_login_password'], ['value' => 'SecretPass123', 'group' => 'sms']);
        SiteSetting::updateOrCreate(['key' => 'zong_cbs_mask'], ['value' => 'ALF-GB', 'group' => 'sms']);
    }

    public function test_sms_templates_index_displays_registered_applicants_and_bulk_sms_controls(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.sms-templates.index'));

        $response->assertOk()
            ->assertSee('Send Bulk SMS to Registered Applicants &amp; Manual Numbers', false)
            ->assertSee('Select All Registered Applicants')
            ->assertSee('Karim Ullah')
            ->assertSee('Zainab Bibi')
            ->assertSee('Hassan Raza')
            ->assertSee('0345-8161101');
    }

    public function test_non_admin_cannot_send_bulk_sms(): void
    {
        $response = $this->actingAs($this->ddGilgit)
            ->post(route('admin.sms-templates.send-bulk'), [
                'message' => 'Unauthorized bulk message',
                'select_all_applicants' => '1',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_super_admin_can_send_bulk_sms_to_selected_applicants_and_manual_numbers_and_see_delivery_report(): void
    {
        $this->configureLiveGateway();

        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::response('0|success|5220980999', 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->post(route('admin.sms-templates.send-bulk'), [
                'message' => 'Important notice: Collect your subsidized wheat seed from Gilgit center.',
                'language' => 'en',
                'applicant_ids' => [$this->applicantOne->id, $this->applicantTwo->id],
                'manual_numbers' => "0300-5551234, 923458161101", // second duplicates applicantOne and should deduplicate
            ]);

        $response->assertOk()
            ->assertSee('Bulk SMS Delivery Report')
            ->assertSee('Karim Ullah')
            ->assertSee('923458161101')
            ->assertSee('Zainab Bibi')
            ->assertSee('923458161102')
            ->assertSee('Manual Number')
            ->assertSee('923005551234')
            ->assertSee('Delivered / Sent')
            ->assertSee('5220980999');

        $this->assertDatabaseCount('sms_logs', 3);
        $this->assertDatabaseHas('sms_logs', [
            'event_key' => 'bulk_admin',
            'recipient_phone' => '923458161101',
            'recipient_name' => 'Karim Ullah',
            'status' => 'sent',
            'zong_sms_id' => '5220980999',
        ]);
        $this->assertDatabaseHas('sms_logs', [
            'event_key' => 'bulk_admin',
            'recipient_phone' => '923005551234',
            'recipient_name' => 'Manual Number',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('sms_logs', [
            'recipient_phone' => '923458161103',
        ]);
    }

    public function test_super_admin_can_select_all_registered_applicants_and_view_per_user_delivery_statuses(): void
    {
        $this->configureLiveGateway();

        Http::fake([
            'cbs.zong.com.pk/reachrestapi/home/SendQuickSMS*' => Http::sequence()
                ->push('0|success|5220981001', 200)
                ->push('0|success|5220981002', 200)
                ->push('101|Invalid Destination Number', 200)
                ->push('0|success|5220981004', 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->followingRedirects()
            ->post(route('admin.sms-templates.send-bulk'), [
                'message' => 'All applicants notice: Verification schedule updated.',
                'language' => 'auto',
                'select_all_applicants' => '1',
                'manual_numbers' => '0311-9988776',
            ]);

        $response->assertOk()
            ->assertSee('Bulk SMS Delivery Report')
            ->assertSee('Total: 4')
            ->assertSee('Delivered / Sent: 3')
            ->assertSee('Failed: 1')
            ->assertSee('Hassan Raza')
            ->assertSee('Karim Ullah')
            ->assertSee('Zainab Bibi')
            ->assertSee('923119988776');

        $this->assertDatabaseCount('sms_logs', 4);
        $this->assertDatabaseHas('sms_logs', [
            'recipient_phone' => '923458161102',
            'status' => 'failed',
            'zong_status_code' => '101',
        ]);
    }

    public function test_bulk_sms_requires_at_least_one_recipient(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.sms-templates.send-bulk'), [
                'message' => 'Hello applicants',
                'language' => 'en',
                'manual_numbers' => '',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('sms_logs', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOnlySmsAndGbpayAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $accountsOfficer;
    private User $secretary;
    private User $directorAgri;
    private User $districtOfficer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();
        $this->secretary = User::where('email', 'secretary@alf.gb.gov.pk')->firstOrFail();

        $agriDept = Department::where('slug', 'agriculture')->firstOrFail();
        $gilgit = District::where('code', 'GLT')->firstOrFail();

        $this->accountsOfficer = User::factory()->create([
            'email' => 'accounts.test@alf.gb.gov.pk',
            'department_id' => $agriDept->id,
            'district_id' => $gilgit->id,
            'is_active' => true,
        ]);
        $this->accountsOfficer->syncRoles(['Accounts Officer']);

        $this->directorAgri = User::factory()->create([
            'email' => 'director.agri.test@alf.gb.gov.pk',
            'department_id' => $agriDept->id,
            'is_active' => true,
        ]);
        $this->directorAgri->syncRoles(['Director Agriculture']);

        $this->districtOfficer = User::factory()->create([
            'email' => 'do.test@alf.gb.gov.pk',
            'department_id' => $agriDept->id,
            'district_id' => $gilgit->id,
            'is_active' => true,
        ]);
        $this->districtOfficer->syncRoles(['District Officer']);
    }

    public function test_super_admin_can_access_gbpay_and_sms_pages(): void
    {
        // GBPay index
        $this->actingAs($this->superAdmin)
            ->get(route('admin.gbpay.index'))
            ->assertOk();

        // GBPay API tester
        $this->actingAs($this->superAdmin)
            ->get(route('admin.gbpay.test'))
            ->assertOk();

        // SMS Templates index
        $this->actingAs($this->superAdmin)
            ->get(route('admin.sms-templates.index'))
            ->assertOk();
    }

    public function test_accounts_officer_is_forbidden_from_gbpay_and_sms_pages(): void
    {
        $this->actingAs($this->accountsOfficer)
            ->get(route('admin.gbpay.index'))
            ->assertForbidden();

        $this->actingAs($this->accountsOfficer)
            ->get(route('admin.gbpay.test'))
            ->assertForbidden();

        $this->actingAs($this->accountsOfficer)
            ->get(route('admin.sms-templates.index'))
            ->assertForbidden();
    }

    public function test_secretary_is_forbidden_from_gbpay_and_sms_pages(): void
    {
        $this->actingAs($this->secretary)
            ->get(route('admin.gbpay.index'))
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->get(route('admin.gbpay.test'))
            ->assertForbidden();

        $this->actingAs($this->secretary)
            ->get(route('admin.sms-templates.index'))
            ->assertForbidden();
    }

    public function test_director_and_district_officer_are_forbidden_from_gbpay_and_sms_pages(): void
    {
        $this->actingAs($this->directorAgri)
            ->get(route('admin.gbpay.index'))
            ->assertForbidden();

        $this->actingAs($this->directorAgri)
            ->get(route('admin.sms-templates.index'))
            ->assertForbidden();

        $this->actingAs($this->districtOfficer)
            ->get(route('admin.gbpay.index'))
            ->assertForbidden();

        $this->actingAs($this->districtOfficer)
            ->get(route('admin.sms-templates.index'))
            ->assertForbidden();
    }

    public function test_sidebar_displays_gbpay_and_sms_only_for_super_admin(): void
    {
        // Super Admin sees both
        $adminView = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $adminView->assertSee('GBPay Gateway (PSID)');
        $adminView->assertSee('SMS Gateway');

        // Accounts Officer does NOT see GBPay or SMS
        $aoView = $this->actingAs($this->accountsOfficer)->get(route('admin.dashboard'));
        $aoView->assertDontSee('GBPay Gateway (PSID)');
        $aoView->assertDontSee('SMS Gateway');

        // Secretary does NOT see GBPay or SMS
        $secView = $this->actingAs($this->secretary)->get(route('admin.dashboard'));
        $secView->assertDontSee('GBPay Gateway (PSID)');
        $secView->assertDontSee('SMS Gateway');
    }
}


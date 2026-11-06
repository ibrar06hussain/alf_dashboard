<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserMultipleRolesAndPagePermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Department $agriDept;
    protected District $gilgitDist;
    protected District $diamerDist;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles & permissions are registered
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock',
            'Director Fisheries', 'Division Director', 'District Deputy Director',
            'District Officer', 'Accounts Officer', 'Distribution Officer', 'Field Officer',
        ];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $perms = [
            'pages.dashboard', 'pages.cm_dashboard', 'pages.wheat_mis', 'pages.stats',
            'pages.applications', 'pages.interests', 'pages.gbpay', 'pages.schemes',
            'pages.form_builder', 'pages.challan_templates', 'pages.stock', 'pages.organizations',
            'pages.departments', 'pages.districts', 'pages.cms', 'pages.sms', 'pages.users',
            'pages.roles', 'pages.audit_logs',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->agriDept = Department::firstOrCreate(
            ['slug' => 'agriculture'],
            ['name' => 'Agriculture', 'display_order' => 1]
        );

        $div = Division::firstOrCreate(
            ['code' => 'GLT_DIV'],
            ['name' => 'Gilgit Division', 'headquarters' => 'Gilgit', 'is_active' => true]
        );

        $this->gilgitDist = District::firstOrCreate(
            ['code' => 'GLT'],
            ['name' => 'Gilgit']
        );
        $this->diamerDist = District::firstOrCreate(
            ['code' => 'DMR'],
            ['name' => 'Diamer']
        );

        $div->districts()->syncWithoutDetaching([$this->gilgitDist->id]);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin_test@alf.gb.gov.pk',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');
    }

    public function test_admin_can_create_user_with_multiple_roles(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.users.store'), [
            'name' => 'Multi Role Officer',
            'email' => 'multirole@alf.gb.gov.pk',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['District Deputy Director', 'Accounts Officer'],
            'district_ids' => [$this->gilgitDist->id],
            'department_ids' => [$this->agriDept->id],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'multirole@alf.gb.gov.pk']);

        $user = User::where('email', 'multirole@alf.gb.gov.pk')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('District Deputy Director'));
        $this->assertTrue($user->hasRole('Accounts Officer'));
        $this->assertCount(2, $user->roles);
    }

    public function test_admin_can_assign_custom_page_level_permissions_to_user(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.users.store'), [
            'name' => 'Custom Perms Officer',
            'email' => 'customperms@alf.gb.gov.pk',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Field Officer'],
            'permissions' => ['pages.wheat_mis', 'pages.stock'],
            'district_ids' => [$this->gilgitDist->id],
            'department_ids' => [$this->agriDept->id],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'customperms@alf.gb.gov.pk')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Field Officer'));
        $this->assertTrue($user->hasDirectPermission('pages.wheat_mis'));
        $this->assertTrue($user->hasDirectPermission('pages.stock'));
        $this->assertFalse($user->hasDirectPermission('pages.users'));
    }

    public function test_user_with_direct_wheat_mis_page_permission_can_access_wheat_mis_page(): void
    {
        // Field Officer normally does not have access to stock or wheat-mis route unless granted
        $user = User::factory()->create([
            'email' => 'officer_page_access@alf.gb.gov.pk',
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgitDist->id,
            'is_active' => true,
        ]);
        $user->assignRole('Field Officer');
        $user->givePermissionTo('pages.wheat_mis');

        $response = $this->actingAs($user)->get(route('admin.wheat-mis.index'));
        $response->assertOk();
    }

    public function test_user_with_direct_stock_permission_can_access_stock_page(): void
    {
        $user = User::factory()->create([
            'email' => 'stock_officer@alf.gb.gov.pk',
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgitDist->id,
            'is_active' => true,
        ]);
        $user->assignRole('Field Officer');
        $user->givePermissionTo('pages.stock');

        $response = $this->actingAs($user)->get(route('admin.stock.index'));
        $response->assertOk();
    }

    public function test_admin_can_update_roles_and_permissions_on_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'update_test@alf.gb.gov.pk',
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgitDist->id,
            'is_active' => true,
        ]);
        $user->assignRole('Field Officer');

        $response = $this->actingAs($this->superAdmin)->put(route('admin.users.update', $user), [
            'name' => 'Updated User Name',
            'email' => 'update_test@alf.gb.gov.pk',
            'roles' => ['Accounts Officer', 'District Officer'],
            'permissions' => ['pages.cm_dashboard', 'pages.wheat_mis'],
            'district_ids' => [$this->gilgitDist->id],
            'department_ids' => [$this->agriDept->id],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertFalse($user->hasRole('Field Officer'));
        $this->assertTrue($user->hasRole('Accounts Officer'));
        $this->assertTrue($user->hasRole('District Officer'));
        $this->assertTrue($user->hasDirectPermission('pages.cm_dashboard'));
        $this->assertTrue($user->hasDirectPermission('pages.wheat_mis'));
    }

    public function test_backward_compatibility_with_single_role_input(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(route('admin.users.store'), [
            'name' => 'Single Role User',
            'email' => 'single@alf.gb.gov.pk',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'District Deputy Director',
            'district_ids' => [$this->gilgitDist->id],
            'department_ids' => [$this->agriDept->id],
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'single@alf.gb.gov.pk')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('District Deputy Director'));
    }
}


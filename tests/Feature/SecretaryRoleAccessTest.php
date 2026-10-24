<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Scheme;
use App\Models\User;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretaryRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;
    private User $superAdmin;
    private User $directorAgri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->secretary = User::where('email', 'secretary@alf.gb.gov.pk')->firstOrFail();
        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();

        $agriDept = Department::where('slug', 'agriculture')->firstOrFail();
        $this->directorAgri = User::updateOrCreate(
            ['email' => 'director.agriculture@alf.gb.gov.pk'],
            [
                'name' => 'Director General Agriculture GB',
                'password' => bcrypt('password'),
                'department_id' => $agriDept->id,
                'is_active' => true,
            ]
        );
        $this->directorAgri->syncRoles(['Director Agriculture']);

        // Ensure all 3 departments exist
        $agri = Department::firstOrCreate(['slug' => 'agriculture'], ['name' => 'Agriculture', 'display_order' => 1]);
        Department::firstOrCreate(['slug' => 'livestock'], ['name' => 'Livestock & Dairy Development', 'display_order' => 2]);
        Department::firstOrCreate(['slug' => 'fisheries'], ['name' => 'Fisheries', 'display_order' => 3]);

        Scheme::firstOrCreate(
            ['slug' => 'wheat-seed-cost-sharing'],
            [
                'department_id' => $agri->id,
                'title' => 'Distribution of Wheat Seed (50:50 Cost Sharing)',
                'status' => 'active',
                'type' => 'running',
                'scope' => 'gb',
            ]
        );
    }

    /**
     * Secretary can view the Dashboard with 3 departments and schemes status.
     */
    public function test_secretary_dashboard_displays_three_departments_performance_and_schemes(): void
    {
        $response = $this->actingAs($this->secretary)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Secretary Executive Dashboard');
        $response->assertSee('Agriculture');
        $response->assertSee('Livestock');
        $response->assertSee('Fisheries');
        $response->assertSee('Department Performance');
        $response->assertSee('Projects & Schemes of All Three Departments', false);
        $response->assertSee('Distribution of Wheat Seed (50:50 Cost Sharing)');
        $response->assertSee('Open CM Dashboard');
        $response->assertSee('Open Wheat Seed MIS');
        $response->assertSee('View Citizen Interests');
    }

    /**
     * Secretary CAN access Executive Dashboards and Analytics.
     */
    public function test_secretary_can_access_executive_portals(): void
    {
        // 1. CM Dashboard
        $this->actingAs($this->secretary)
            ->get(route('admin.cm-dashboard.index'))
            ->assertOk();

        // 2. Wheat Seed MIS
        $this->actingAs($this->secretary)
            ->get(route('admin.wheat-mis.index'))
            ->assertOk();

        // 3. Citizen Interests
        $this->actingAs($this->secretary)
            ->get(route('admin.interests.index'))
            ->assertOk();

        // 4. Applications Register
        $this->actingAs($this->secretary)
            ->get(route('admin.applications.index'))
            ->assertOk();
    }

    /**
     * Secretary is strictly FORBIDDEN from Web Application Modification pages.
     */
    public function test_secretary_is_forbidden_from_web_modification_pages(): void
    {
        // Departments
        $this->actingAs($this->secretary)
            ->get(route('admin.departments.index'))
            ->assertStatus(403);

        // Districts
        $this->actingAs($this->secretary)
            ->get(route('admin.districts.index'))
            ->assertStatus(403);

        // Carousel
        $this->actingAs($this->secretary)
            ->get(route('admin.carousel.index'))
            ->assertStatus(403);

        // Site Media
        $this->actingAs($this->secretary)
            ->get(route('admin.media.edit'))
            ->assertStatus(403);

        // Site Information
        $this->actingAs($this->secretary)
            ->get(route('admin.site-info.edit'))
            ->assertStatus(403);

        // Schemes & Proposed Schemes
        $this->actingAs($this->secretary)
            ->get(route('admin.schemes.index'))
            ->assertStatus(403);

        $this->actingAs($this->secretary)
            ->get(route('admin.schemes.index', ['type' => 'proposed']))
            ->assertStatus(403);

        // Application Forms
        $this->actingAs($this->secretary)
            ->get(route('admin.form-builder.index'))
            ->assertStatus(403);

        // Users
        $this->actingAs($this->secretary)
            ->get(route('admin.users.index'))
            ->assertStatus(403);
    }

    /**
     * Secretary sidebar does NOT display web modification links.
     */
    public function test_secretary_sidebar_does_not_contain_web_modification_links(): void
    {
        $response = $this->actingAs($this->secretary)->get(route('admin.dashboard'));

        $response->assertOk();

        // Secretary should see executive oversight items
        $response->assertSee('CM Dashboard');
        $response->assertSee('Wheat Seed MIS');
        $response->assertSee('Interests');
        $response->assertSee('Applications');

        // Secretary should NOT see modification links in sidebar
        $response->assertDontSee('route(\'admin.departments.index\')', false);
        $response->assertDontSee('route(\'admin.districts.index\')', false);
        $response->assertDontSee('route(\'admin.carousel.index\')', false);
        $response->assertDontSee('route(\'admin.media.edit\')', false);
        $response->assertDontSee('route(\'admin.site-info.edit\')', false);
        $response->assertDontSee('route(\'admin.users.index\')', false);
    }

    /**
     * Super Admin retains full access to Web Application Modification pages.
     */
    public function test_super_admin_can_access_web_modification_pages(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.departments.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.districts.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.carousel.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.schemes.index'))
            ->assertOk();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }
}

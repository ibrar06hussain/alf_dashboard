<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Office;
use App\Models\Scheme;
use App\Models\SchemeDistrict;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DistrictOfficerDashboardScopingTest extends TestCase
{
    use RefreshDatabase;

    private District $gilgit;
    private District $diamer;
    private Department $agriculture;
    private User $ddGilgit;
    private User $ddDiamer;
    private User $directorAgri;
    private User $secretary;
    private User $superAdmin;
    private Scheme $wheatScheme;
    private Application $gilgitApp;
    private Application $diamerApp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $this->diamer = District::where('name', 'Diamer')->firstOrFail();
        $this->agriculture = Department::where('slug', 'agriculture')->firstOrFail();

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();
        $this->secretary = User::where('email', 'secretary@alf.gb.gov.pk')->firstOrFail();
        $this->ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $this->ddDiamer = User::where('email', 'dd.diamer@alf.gb.gov.pk')->firstOrFail();

        $this->directorAgri = User::updateOrCreate(
            ['email' => 'director.agriculture@alf.gb.gov.pk'],
            [
                'name' => 'Director General Agriculture GB',
                'password' => bcrypt('password'),
                'department_id' => $this->agriculture->id,
                'is_active' => true,
            ]
        );
        $this->directorAgri->syncRoles(['Director Agriculture']);

        // Wheat seed scheme
        $this->wheatScheme = Scheme::firstOrCreate(
            ['id' => 1],
            [
                'department_id' => $this->agriculture->id,
                'title' => 'Distribution of Wheat Seed (Cost Sharing 50:50)',
                'slug' => 'distribution-of-wheat-seed-cost-sharing',
                'scope' => 'gb',
                'status' => 'active',
            ]
        );

        // Quotas
        SchemeDistrict::updateOrCreate(
            ['scheme_id' => $this->wheatScheme->id, 'district_id' => $this->gilgit->id],
            ['quota' => 250]
        );
        SchemeDistrict::updateOrCreate(
            ['scheme_id' => $this->wheatScheme->id, 'district_id' => $this->diamer->id],
            ['quota' => 150]
        );

        // Form & Fields
        $form = Form::firstOrCreate(
            ['scheme_id' => $this->wheatScheme->id],
            ['title' => 'Wheat Seed Application Form', 'is_published' => true]
        );
        $fieldLand = FormField::firstOrCreate(
            ['form_id' => $form->id, 'field_key' => 'land_kanals'],
            ['field_label' => 'Land Available (Kanals)', 'field_type' => 'number', 'display_order' => 1]
        );

        // Gilgit Applicant & Application
        $applicantGilgit = Applicant::create([
            'full_name' => 'Ali Ahmad Gilgiti',
            'cnic' => '71101-1111111-1',
            'phone' => '03451111111',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit Tehsil',
            'village' => 'Danyore',
        ]);
        $this->gilgitApp = Application::create([
            'application_no' => 'ALF-GLT-1001',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $applicantGilgit->id,
            'district_id' => $this->gilgit->id,
            'form_id' => $form->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ApplicationFieldValue::create([
            'application_id' => $this->gilgitApp->id,
            'form_field_id' => $fieldLand->id,
            'value' => '8',
        ]);

        // Diamer Applicant & Application
        $applicantDiamer = Applicant::create([
            'full_name' => 'Karim Dad Diameri',
            'cnic' => '71201-2222222-2',
            'phone' => '03452222222',
            'district_id' => $this->diamer->id,
            'tehsil' => 'Chilas',
            'village' => 'Bunji',
        ]);
        $this->diamerApp = Application::create([
            'application_no' => 'ALF-DMR-2002',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $applicantDiamer->id,
            'district_id' => $this->diamer->id,
            'form_id' => $form->id,
            'status' => 'directorate_approved',
            'submitted_at' => now(),
        ]);
        ApplicationFieldValue::create([
            'application_id' => $this->diamerApp->id,
            'form_field_id' => $fieldLand->id,
            'value' => '12',
        ]);
    }

    public function test_officer_model_correctly_classifies_roles(): void
    {
        // Directors, Secretary, and Super Admin
        $this->assertTrue($this->superAdmin->isDirectorSecretaryOrAdmin());
        $this->assertFalse($this->superAdmin->isDistrictScopedOfficer());

        $this->assertTrue($this->secretary->isDirectorSecretaryOrAdmin());
        $this->assertFalse($this->secretary->isDistrictScopedOfficer());

        $this->assertTrue($this->directorAgri->isDirectorSecretaryOrAdmin());
        $this->assertFalse($this->directorAgri->isDistrictScopedOfficer());

        // District Officers
        $this->assertFalse($this->ddGilgit->isDirectorSecretaryOrAdmin());
        $this->assertTrue($this->ddGilgit->isDistrictScopedOfficer());

        $this->assertFalse($this->ddDiamer->isDirectorSecretaryOrAdmin());
        $this->assertTrue($this->ddDiamer->isDistrictScopedOfficer());
    }

    public function test_district_officer_main_dashboard_only_shows_respective_district(): void
    {
        // DD Gilgit accesses Main Dashboard
        $response = $this->actingAs($this->ddGilgit)->get(route('admin.dashboard'));
        $response->assertOk();

        // Should see Gilgit District branding
        $response->assertSee('Gilgit District Dashboard');
        $response->assertSee('Overview of ALF schemes, applications, and statistics for Gilgit District');
        $response->assertSee('ALF-GLT-1001');
        $response->assertSee('Ali Ahmad Gilgiti');

        // MUST NOT see Diamer application or Diamer applicant
        $response->assertDontSee('ALF-DMR-2002');
        $response->assertDontSee('Karim Dad Diameri');

        // Check KPI counts passed to view
        $viewKpis = $response->viewData('kpis');
        $this->assertEquals(1, $viewKpis['applications'], 'DD Gilgit should see exactly 1 application (Gilgit only)');
        $this->assertEquals(1, $viewKpis['applicants'], 'DD Gilgit should see exactly 1 applicant (Gilgit only)');
        $this->assertEquals(1, $viewKpis['pending'], 'DD Gilgit should see 1 pending application');
        $this->assertEquals(0, $viewKpis['directorate_approved'], 'DD Gilgit should see 0 approved (the approved one belongs to Diamer)');

        // Check recent applications passed to view
        $recentApps = $response->viewData('recentApplications');
        $this->assertCount(1, $recentApps);
        $this->assertEquals('ALF-GLT-1001', $recentApps->first()->application_no);
    }

    public function test_diamer_officer_main_dashboard_only_shows_diamer_district(): void
    {
        // DD Diamer accesses Main Dashboard
        $response = $this->actingAs($this->ddDiamer)->get(route('admin.dashboard'));
        $response->assertOk();

        // Should see Diamer District branding
        $response->assertSee('Diamer District Dashboard');
        $response->assertSee('ALF-DMR-2002');
        $response->assertSee('Karim Dad Diameri');

        // MUST NOT see Gilgit application or applicant
        $response->assertDontSee('ALF-GLT-1001');
        $response->assertDontSee('Ali Ahmad Gilgiti');

        // Check KPI counts passed to view
        $viewKpis = $response->viewData('kpis');
        $this->assertEquals(1, $viewKpis['applications'], 'DD Diamer should see exactly 1 application');
        $this->assertEquals(1, $viewKpis['applicants'], 'DD Diamer should see exactly 1 applicant');
        $this->assertEquals(0, $viewKpis['pending'], 'Diamer has 0 pending');
        $this->assertEquals(1, $viewKpis['directorate_approved'], 'Diamer has 1 approved');
    }

    public function test_director_and_super_admin_see_all_districts_on_main_dashboard(): void
    {
        // Super Admin sees both Gilgit and Diamer applications
        $response = $this->actingAs($this->superAdmin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('ALF-GLT-1001');
        $response->assertSee('ALF-DMR-2002');

        $viewKpis = $response->viewData('kpis');
        $this->assertEquals(2, $viewKpis['applications'], 'Super Admin should see all 2 applications');

        // Secretary sees both
        $secResponse = $this->actingAs($this->secretary)->get(route('admin.dashboard'));
        $secResponse->assertOk();
        $secResponse->assertSee('ALF-GLT-1001');
        $secResponse->assertSee('ALF-DMR-2002');

        // Director Agriculture sees both
        $dirResponse = $this->actingAs($this->directorAgri)->get(route('admin.dashboard'));
        $dirResponse->assertOk();
        $dirResponse->assertSee('ALF-GLT-1001');
        $dirResponse->assertSee('ALF-DMR-2002');
    }

    public function test_district_officer_wheat_seed_mis_dashboard_only_shows_respective_district(): void
    {
        // DD Gilgit accesses Wheat Seed MIS Dashboard
        $response = $this->actingAs($this->ddGilgit)->get(route('admin.wheat-mis.index'));
        $response->assertOk();

        // Should see Gilgit beneficiary and quota (250 bags)
        $response->assertSee('ALF-GLT-1001');
        $response->assertSee('Ali Ahmad Gilgiti');

        // Demand & Supply gap table should contain Gilgit
        $demandSupplyGap = $response->viewData('demandSupplyGap');
        $this->assertCount(1, $demandSupplyGap, 'DD Gilgit should only see Gilgit in demand-supply gap table');
        $this->assertEquals('Gilgit', $demandSupplyGap->first()['district_name']);
        $this->assertEquals(250, $demandSupplyGap->first()['quota']);

        // MUST NOT see Diamer application or Diamer in demand-supply gap
        $response->assertDontSee('ALF-DMR-2002');
        $response->assertDontSee('Karim Dad Diameri');

        // KPIs check
        $kpis = $response->viewData('kpis');
        $this->assertEquals(1, $kpis['total_applications'], 'DD Gilgit should only count Gilgit applications');
        $this->assertEquals(250, $kpis['bags']['allocated'], 'DD Gilgit should only see Gilgit allocated quota (250)');

        // District rates table check
        $ratesTable = $response->viewData('districtRatesTable');
        $this->assertCount(1, $ratesTable, 'Rates table should only contain Gilgit');
        $this->assertEquals('Gilgit', $ratesTable->first()->district_name);
    }

    public function test_district_officer_wheat_seed_mis_cannot_bypass_scope_via_url_tampering(): void
    {
        // DD Gilgit attempts to query Diamer by passing ?district_id=<diamer_id>
        $response = $this->actingAs($this->ddGilgit)->get(route('admin.wheat-mis.index', [
            'district_id' => $this->diamer->id,
        ]));

        $response->assertOk();

        // The system must lock them to Gilgit or return no records from Diamer
        $response->assertDontSee('ALF-DMR-2002');
        $response->assertDontSee('Karim Dad Diameri');

        // Demand Supply Gap table still only has Gilgit
        $demandSupplyGap = $response->viewData('demandSupplyGap');
        $this->assertCount(1, $demandSupplyGap);
        $this->assertEquals('Gilgit', $demandSupplyGap->first()['district_name']);
    }

    public function test_district_officer_wheat_mis_beneficiaries_ajax_strictly_scoped(): void
    {
        // DD Gilgit calls AJAX beneficiaries endpoint
        $response = $this->actingAs($this->ddGilgit)->getJson(route('admin.wheat-mis.beneficiaries', [
            'district_id' => $this->diamer->id, // attempt tamper
        ]));

        $response->assertOk();
        $json = $response->json();

        // Total records returned must be for Gilgit
        $data = $json['data'];
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('Gilgit', $item['district_name']);
            $this->assertNotEquals('Diamer', $item['district_name']);
        }
    }

    public function test_district_officer_wheat_mis_analytics_ajax_strictly_scoped(): void
    {
        // DD Gilgit calls AJAX analytics endpoint
        $response = $this->actingAs($this->ddGilgit)->getJson(route('admin.wheat-mis.analytics'));
        $response->assertOk();
        $json = $response->json();

        // Demand supply labels must only include Gilgit
        $labels = $json['demand_supply']['labels'];
        $this->assertContains('Gilgit', $labels);
        $this->assertNotContains('Diamer', $labels);
    }

    public function test_district_officer_is_forbidden_from_cm_dashboard_by_role(): void
    {
        // District Deputy Director cannot access Chief Minister executive dashboard
        $response = $this->actingAs($this->ddGilgit)->get(route('admin.cm-dashboard.index'));
        $response->assertStatus(403);

        $analyticsResponse = $this->actingAs($this->ddGilgit)->getJson(route('admin.cm-dashboard.analytics'));
        $analyticsResponse->assertStatus(403);
    }
}

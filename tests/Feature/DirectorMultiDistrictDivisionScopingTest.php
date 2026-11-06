<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Division;
use App\Models\Form;
use App\Models\Office;
use App\Models\Scheme;
use App\Models\User;
use App\Models\UserOfficeAssignment;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectorMultiDistrictDivisionScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);
    }

    private function createDistrictApplication(int $districtId, int $departmentId, string $status = 'pending'): Application
    {
        $scheme = Scheme::create([
            'department_id' => $departmentId,
            'title' => 'Test Scheme for Dept ' . $departmentId . ' Dist ' . $districtId,
            'slug' => 'test-scheme-' . uniqid(),
            'scope' => 'district',
            'type' => 'running',
            'status' => 'active',
            'is_active' => true,
        ]);

        $scheme->districts()->attach($districtId, ['quota' => 100]);

        $applicant = Applicant::create([
            'full_name' => 'Applicant ' . uniqid(),
            'cnic' => '71101-' . rand(1000000, 9999999) . '-1',
            'phone' => '0345' . rand(1000000, 9999999),
            'district_id' => $districtId,
            'tehsil' => 'Sample Tehsil',
            'village' => 'Sample Village',
        ]);

        $form = Form::create([
            'scheme_id' => $scheme->id,
            'title' => 'Sample Form',
            'is_published' => true,
        ]);

        return Application::create([
            'application_no' => Application::generateApplicationNo(),
            'scheme_id' => $scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $districtId,
            'form_id' => $form->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    public function test_director_created_for_gilgit_division_and_agriculture_sees_only_assigned_districts_and_department(): void
    {
        $superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->first();
        $this->actingAs($superAdmin);

        $agriDept = Department::where('slug', 'agriculture')->firstOrFail();
        $liveDept = Department::where('slug', 'livestock')->firstOrFail();

        $gilgitDiv = Division::where('name', 'Gilgit Division')->firstOrFail();
        $gilgitDist = District::where('name', 'Gilgit')->firstOrFail();
        $nagarDist = District::where('name', 'Nagar')->firstOrFail();
        $hunzaDist = District::where('name', 'Hunza')->firstOrFail();
        $ghizerDist = District::where('name', 'Ghizer')->firstOrFail();

        $diamerDist = District::where('name', 'Diamer')->firstOrFail();
        $skarduDist = District::where('name', 'Skardu')->firstOrFail();

        // 1. Create a user via Admin UserController with Division Director role, Gilgit Division, and Agriculture Department
        $response = $this->post(route('admin.users.store'), [
            'name' => 'Director Agriculture Gilgit Region',
            'email' => 'director.agri.gilgit@alf.gb.gov.pk',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'Director Agriculture',
            'division_id' => $gilgitDiv->id,
            'department_ids' => [$agriDept->id],
            'district_ids' => [$gilgitDist->id, $nagarDist->id, $hunzaDist->id, $ghizerDist->id],
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $directorUser = User::where('email', 'director.agri.gilgit@alf.gb.gov.pk')->firstOrFail();

        // Check authorized IDs
        $authorizedDistricts = $directorUser->authorizedDistrictIds();
        $authorizedDepts = $directorUser->authorizedDepartmentIds();

        $this->assertEqualsCanonicalizing(
            [$gilgitDist->id, $nagarDist->id, $hunzaDist->id, $ghizerDist->id],
            $authorizedDistricts
        );
        $this->assertEqualsCanonicalizing([$agriDept->id], $authorizedDepts);

        // 2. Create sample applications in various districts and departments
        $gilgitAgriApp = $this->createDistrictApplication($gilgitDist->id, $agriDept->id);
        $nagarAgriApp = $this->createDistrictApplication($nagarDist->id, $agriDept->id);
        $hunzaAgriApp = $this->createDistrictApplication($hunzaDist->id, $agriDept->id);
        $ghizerAgriApp = $this->createDistrictApplication($ghizerDist->id, $agriDept->id);

        $gilgitLiveApp = $this->createDistrictApplication($gilgitDist->id, $liveDept->id);
        $diamerAgriApp = $this->createDistrictApplication($diamerDist->id, $agriDept->id);
        $skarduAgriApp = $this->createDistrictApplication($skarduDist->id, $agriDept->id);

        // 3. Act as this Director
        $this->actingAs($directorUser);

        // Can view Gilgit division Agriculture applications
        $this->get(route('admin.applications.show', $gilgitAgriApp))->assertOk();
        $this->get(route('admin.applications.show', $nagarAgriApp))->assertOk();
        $this->get(route('admin.applications.show', $hunzaAgriApp))->assertOk();
        $this->get(route('admin.applications.show', $ghizerAgriApp))->assertOk();

        // CANNOT view different department in Gilgit (Livestock is 403)
        $this->get(route('admin.applications.show', $gilgitLiveApp))->assertStatus(403);

        // CANNOT view Diamer or Skardu Agriculture applications (different division is 403)
        $this->get(route('admin.applications.show', $diamerAgriApp))->assertStatus(403);
        $this->get(route('admin.applications.show', $skarduAgriApp))->assertStatus(403);

        // 4. Check Applications Index filtering
        $indexResp = $this->get(route('admin.applications.index'));
        $indexResp->assertOk();
        $indexResp->assertSee($gilgitAgriApp->application_no);
        $indexResp->assertSee($nagarAgriApp->application_no);
        $indexResp->assertDontSee($gilgitLiveApp->application_no);
        $indexResp->assertDontSee($diamerAgriApp->application_no);
        $indexResp->assertDontSee($skarduAgriApp->application_no);

        // 5. Check Dashboard scoping
        $dashResp = $this->get(route('admin.dashboard'));
        $dashResp->assertOk();
        $dashResp->assertSee('Director Agriculture Gilgit Region');

        // 6. Check Schemes index scoping
        $schemesResp = $this->get(route('admin.schemes.index'));
        $schemesResp->assertOk();
        $schemesResp->assertSee($gilgitAgriApp->scheme->title);
        $schemesResp->assertDontSee($gilgitLiveApp->scheme->title);
    }

    public function test_division_director_with_agriculture_sees_only_their_division_agriculture_schemes(): void
    {
        $superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->first();
        $this->actingAs($superAdmin);

        $agriDept = Department::where('slug', 'agriculture')->firstOrFail();
        $liveDept = Department::where('slug', 'livestock')->firstOrFail();
        $gilgitDiv = Division::where('name', 'Gilgit Division')->firstOrFail();
        $gilgitDist = District::where('name', 'Gilgit')->firstOrFail();
        $diamerDist = District::where('name', 'Diamer')->firstOrFail();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Gilgit Division Director Agri Only',
            'email' => 'div.director.agri@alf.gb.gov.pk',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'Division Director',
            'division_id' => $gilgitDiv->id,
            'department_ids' => [$agriDept->id],
            'district_ids' => [$gilgitDist->id],
            'is_active' => '1',
        ]);
        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'div.director.agri@alf.gb.gov.pk')->firstOrFail();
        $this->actingAs($user);

        $agriApp = $this->createDistrictApplication($gilgitDist->id, $agriDept->id);
        $liveApp = $this->createDistrictApplication($gilgitDist->id, $liveDept->id);

        $this->get(route('admin.applications.show', $agriApp))->assertOk();
        $this->get(route('admin.applications.show', $liveApp))->assertStatus(403);
    }
}


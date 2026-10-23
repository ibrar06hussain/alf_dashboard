<?php

namespace Tests\Feature;

use App\Models\AccessAuditLog;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationAssignment;
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

class RbacScopingAndHierarchyTest extends TestCase
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
            'title' => 'Test Scheme for District ' . $districtId,
            'slug' => 'test-scheme-' . uniqid(),
            'scope' => 'district',
            'type' => 'running',
            'is_active' => true,
        ]);

        $applicant = Applicant::create([
            'full_name' => 'Test Farmer ' . uniqid(),
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

    public function test_super_admin_has_zero_beneficiary_approval_authority(): void
    {
        $superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $app = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');

        // Super Admin can view application
        $viewResp = $this->actingAs($superAdmin)->get(route('admin.applications.show', $app));
        $viewResp->assertOk();
        $viewResp->assertDontSee('Update Status');

        // Attempting to approve must fail
        $statusResp = $this->actingAs($superAdmin)->post(route('admin.applications.status', $app), [
            'status' => 'district_verified',
            'verification_note' => 'Unauthorized approval attempt by Super Admin',
        ]);

        $statusResp->assertSessionHas('error');
        $this->assertEquals('pending', $app->fresh()->status);
    }

    public function test_secretary_has_read_only_global_oversight(): void
    {
        $secretary = User::where('email', 'secretary@alf.gb.gov.pk')->first();
        $diamer = District::where('name', 'Diamer')->first();
        $livestock = Department::where('slug', 'livestock')->first();

        $app = $this->createDistrictApplication($diamer->id, $livestock->id, 'pending');

        // Secretary can view globally across any department or district
        $viewResp = $this->actingAs($secretary)->get(route('admin.applications.show', $app));
        $viewResp->assertOk();
        $viewResp->assertDontSee('Update Status');

        // Secretary cannot approve/verify
        $statusResp = $this->actingAs($secretary)->post(route('admin.applications.status', $app), [
            'status' => 'district_verified',
        ]);

        $statusResp->assertSessionHas('error');
        $this->assertEquals('pending', $app->fresh()->status);
    }

    public function test_division_director_is_scoped_to_division_districts_read_only(): void
    {
        $gilgitDivDirector = User::where('email', 'director.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $diamer = District::where('name', 'Diamer')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $gilgitApp = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');
        $diamerApp = $this->createDistrictApplication($diamer->id, $agri->id, 'pending');

        // Gilgit Division Director can view Gilgit application
        $this->actingAs($gilgitDivDirector)
            ->get(route('admin.applications.show', $gilgitApp))
            ->assertOk();

        // Gilgit Division Director CANNOT view Diamer application (403 forbidden)
        $this->actingAs($gilgitDivDirector)
            ->get(route('admin.applications.show', $diamerApp))
            ->assertStatus(403);

        // Gilgit Division Director has zero approval authority
        $this->actingAs($gilgitDivDirector)
            ->post(route('admin.applications.status', $gilgitApp), ['status' => 'district_verified'])
            ->assertSessionHas('error');
    }

    public function test_district_deputy_director_can_verify_and_approve_within_assigned_district(): void
    {
        $ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $diamer = District::where('name', 'Diamer')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $gilgitApp = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');
        $diamerApp = $this->createDistrictApplication($diamer->id, $agri->id, 'pending');

        // DD Gilgit can review and verify pending application in Gilgit
        $resp = $this->actingAs($ddGilgit)->post(route('admin.applications.status', $gilgitApp), [
            'status' => 'district_verified',
            'verification_note' => 'Field survey verified by DD Gilgit',
        ]);
        $resp->assertSessionHas('success');
        $this->assertEquals('district_verified', $gilgitApp->fresh()->status);

        // DD Gilgit is forbidden from accessing Diamer application (403)
        $this->actingAs($ddGilgit)
            ->get(route('admin.applications.show', $diamerApp))
            ->assertStatus(403);

        $this->actingAs($ddGilgit)
            ->post(route('admin.applications.status', $diamerApp), ['status' => 'district_verified'])
            ->assertStatus(403);
    }

    public function test_district_dd_can_assign_field_officer_for_on_site_survey(): void
    {
        $ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->first();
        $fieldGilgit = User::where('email', 'field.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $app = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');

        // District DD delegates task to Field Officer
        $resp = $this->actingAs($ddGilgit)->post(route('admin.applications.assign-field-officer', $app), [
            'assigned_to' => $fieldGilgit->id,
            'task_type' => 'land_verification',
            'due_date' => now()->addDays(2)->toDateString(),
            'notes' => 'Verify water canal access and measure 4 kanals wheat area',
        ]);

        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('application_assignments', [
            'application_id' => $app->id,
            'assigned_to' => $fieldGilgit->id,
            'task_type' => 'land_verification',
            'status' => 'assigned',
        ]);
    }

    public function test_field_officer_can_only_access_assigned_tasks_and_submit_reports(): void
    {
        $fieldGilgit = User::where('email', 'field.gilgit@alf.gb.gov.pk')->first();
        $ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $assignedApp = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');
        $unassignedApp = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');

        // Assign one application to the Field Officer
        $assignment = ApplicationAssignment::create([
            'application_id' => $assignedApp->id,
            'assigned_to' => $fieldGilgit->id,
            'assigned_by' => $ddGilgit->id,
            'task_type' => 'land_verification',
            'status' => 'assigned',
        ]);

        // Field officer can view assigned application
        $this->actingAs($fieldGilgit)
            ->get(route('admin.applications.show', $assignedApp))
            ->assertOk();

        // Field officer CANNOT view unassigned application (403 forbidden)
        $this->actingAs($fieldGilgit)
            ->get(route('admin.applications.show', $unassignedApp))
            ->assertStatus(403);

        // Field officer CANNOT approve or reject applications
        $this->actingAs($fieldGilgit)
            ->post(route('admin.applications.status', $assignedApp), ['status' => 'district_verified'])
            ->assertSessionHas('error');

        // Field officer CAN submit survey report
        $reportResp = $this->actingAs($fieldGilgit)->post(route('admin.applications.assignments.report', $assignment), [
            'status' => 'completed',
            'land_verified_kanals' => 4.5,
            'recommendation' => 'recommended',
            'notes' => 'On-site measurement confirmed 4.5 kanals wheat land',
        ]);

        $reportResp->assertSessionHas('success');
        $assignment->refresh();
        $this->assertEquals('completed', $assignment->status);
        $this->assertEquals(4.5, $assignment->report_data['land_verified_kanals']);
    }

    public function test_tampered_query_parameters_do_not_bypass_scope(): void
    {
        $ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $diamer = District::where('name', 'Diamer')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $gilgitApp = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');
        $diamerApp = $this->createDistrictApplication($diamer->id, $agri->id, 'pending');

        // DD Gilgit requests list tampering with district_id parameter for Diamer
        $resp = $this->actingAs($ddGilgit)->get(route('admin.applications.index', ['district_id' => $diamer->id]));
        $resp->assertOk();

        // Must see Gilgit application, must NEVER leak Diamer application
        $resp->assertSee($gilgitApp->application_no);
        $resp->assertDontSee($diamerApp->application_no);
    }

    public function test_access_audit_logs_record_privileged_operations(): void
    {
        $ddGilgit = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->first();
        $gilgit = District::where('name', 'Gilgit')->first();
        $agri = Department::where('slug', 'agriculture')->first();

        $app = $this->createDistrictApplication($gilgit->id, $agri->id, 'pending');

        $this->actingAs($ddGilgit)->get(route('admin.applications.show', $app));

        $this->assertDatabaseHas('access_audit_logs', [
            'user_id' => $ddGilgit->id,
            'action' => 'application_viewed',
            'target_id' => (string) $app->id,
        ]);
    }
}

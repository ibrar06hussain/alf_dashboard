<?php

namespace Tests\Feature;

use App\Models\AccessAuditLog;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationStatusLog;
use App\Models\ApplicationSurvey;
use App\Models\Department;
use App\Models\District;
use App\Models\Division;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Office;
use App\Models\Scheme;
use App\Models\User;
use App\Services\ApplicationLifecycleService;
use Carbon\Carbon;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ApplicationLifecycleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ApplicationLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);
        $this->service = app(ApplicationLifecycleService::class);
    }

    private function createTestScheme(bool $requiresChallan = false, float $feePerUnit = 3000.00, ?int $departmentId = null): Scheme
    {
        $deptId = $departmentId ?: Department::where('slug', 'agriculture')->value('id');

        return Scheme::create([
            'department_id' => $deptId,
            'title' => $requiresChallan ? 'Wheat Seed Cost-Sharing Scheme' : 'Free Certified Fruit Saplings Scheme',
            'slug' => 'scheme-' . uniqid(),
            'scope' => 'district',
            'type' => 'running',
            'is_active' => true,
            'requires_challan' => $requiresChallan,
            'challan_fee_per_unit' => $feePerUnit,
        ]);
    }

    private function createTestApplication(
        int $districtId,
        bool $requiresChallan = false,
        float $feePerUnit = 3000.00,
        int $bags = 4
    ): Application {
        $scheme = $this->createTestScheme($requiresChallan, $feePerUnit);

        $applicant = Applicant::create([
            'full_name' => 'Muhammad Ali Test',
            'cnic' => '71101-1234567-1',
            'phone' => '03451234567',
            'district_id' => $districtId,
            'tehsil' => 'Gilgit Central',
            'union_council' => 'Jutial',
            'village' => 'Jutial Payeen',
        ]);

        $form = Form::create([
            'scheme_id' => $scheme->id,
            'title' => 'Application Form',
            'is_published' => true,
        ]);

        $landField = FormField::create([
            'form_id' => $form->id,
            'field_label' => 'Available Land (Kanals)',
            'field_key' => 'available_land',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 1,
        ]);

        $bagsField = FormField::create([
            'form_id' => $form->id,
            'field_label' => 'Seed Bags Required',
            'field_key' => 'bags_required',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 2,
        ]);

        $app = Application::create([
            'application_no' => Application::generateApplicationNo(),
            'scheme_id' => $scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $districtId,
            'form_id' => $form->id,
            'status' => 'pending',
            'current_stage' => Application::STAGE_SUBMITTED,
            'submitted_at' => now(),
            'lock_version' => 0,
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $landField->id,
            'value' => '16',
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $bagsField->id,
            'value' => (string) $bags,
        ]);

        return $app->fresh(['scheme', 'applicant', 'fieldValues', 'form']);
    }

    public function test_submission_preserves_records_resolves_hierarchy_and_assigns_to_active_dd(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);

        $processed = $this->service->onSubmission($app);

        // 1. Preserve applicant and form data
        $this->assertEquals('Muhammad Ali Test', $processed->applicant->full_name);
        $this->assertEquals('71101-1234567-1', $processed->applicant->cnic);
        $this->assertEquals('Gilgit Central', $processed->applicant->tehsil);
        $this->assertEquals('Jutial Payeen', $processed->applicant->village);
        $this->assertEquals(4, $processed->getBagsRequired());
        $this->assertEquals(16.0, $processed->getAvailableLandKanals());

        // 2. Identify scheme department and applicant district
        $this->assertEquals($processed->scheme->department_id, $processed->department_id);
        $this->assertEquals($gilgit->id, $processed->district_id);

        // 3. Resolve division using organizational hierarchy
        $expectedDivisionId = Division::where('code', 'GLT_DIV')->value('id');
        $this->assertEquals($expectedDivisionId, $processed->division_id);

        // 4. Automatically assigned to active Deputy Director for Gilgit
        $expectedDd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $this->assertEquals($expectedDd->id, $processed->assigned_to);
        $this->assertFalse((bool) $processed->is_unassigned);

        // 5. Timestamps and initial stage
        $this->assertNotNull($processed->received_at);
        $this->assertNotNull($processed->assigned_at);
        $this->assertEquals(Application::STAGE_SUBMITTED, $processed->current_stage);
        $this->assertNotNull($processed->responsible_office_id);

        // 6. Complete immutable history logged
        $this->assertDatabaseHas('application_status_logs', [
            'application_id' => $processed->id,
            'to_status' => Application::STAGE_SUBMITTED,
            'user_id' => $expectedDd->id,
        ]);

        $this->assertDatabaseHas('access_audit_logs', [
            'target_type' => Application::class,
            'target_id' => (string) $processed->id,
            'action' => 'application_auto_assigned',
        ]);
    }

    public function test_submission_without_active_dd_enters_unassigned_queue_with_audit_trail(): void
    {
        // District with no active DD seeded in agriculture (e.g. Ghanche)
        $ghanche = District::where('name', 'Ghanche')->firstOrFail();
        $app = $this->createTestApplication($ghanche->id);

        $processed = $this->service->onSubmission($app);

        $this->assertTrue((bool) $processed->is_unassigned);
        $this->assertNull($processed->assigned_to);
        $this->assertEquals(Application::STAGE_SUBMITTED, $processed->current_stage);

        $this->assertDatabaseHas('access_audit_logs', [
            'target_type' => Application::class,
            'target_id' => (string) $processed->id,
            'action' => 'application_unassigned_queue',
        ]);
    }

    public function test_dd_alone_can_make_administrative_decisions(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        // DD transitions to UNDER_REVIEW
        $app = $this->service->transition($app, Application::STAGE_UNDER_REVIEW, $dd, ['note' => 'Reviewing application documents.']);
        $this->assertEquals(Application::STAGE_UNDER_REVIEW, $app->current_stage);

        // DD requests correction
        $app = $this->service->transition($app, Application::STAGE_CORRECTION_REQUIRED, $dd, [
            'remarks' => 'Please upload clear copy of land ownership Fard.',
        ]);
        $this->assertEquals(Application::STAGE_CORRECTION_REQUIRED, $app->current_stage);
        $this->assertEquals('Please upload clear copy of land ownership Fard.', $app->correction_remarks);

        // Citizen resubmits correction
        $app = $this->service->submitCorrection($app, ['fard_updated' => true]);
        $this->assertEquals(Application::STAGE_UNDER_REVIEW, $app->current_stage);
        $this->assertNotNull($app->correction_submitted_at);

        // DD can reject
        $app = $this->service->transition($app, Application::STAGE_REJECTED, $dd, [
            'rejection_reason' => 'Applicant does not meet minimum land threshold.',
        ]);
        $this->assertEquals(Application::STAGE_REJECTED, $app->current_stage);
        $this->assertEquals('rejected', $app->status);
        $this->assertEquals('Applicant does not meet minimum land threshold.', $app->rejection_reason);
    }

    public function test_field_officer_cannot_approve_applications(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->service->transition($app, Application::STAGE_APPROVED, $fieldOfficer);
    }

    public function test_complete_field_survey_lifecycle(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        // 1. DD assigns Field Survey
        $survey = $this->service->assignSurvey($app, $dd, $fieldOfficer, [
            'instructions' => 'Physically inspect land parcel and verify irrigation water availability.',
        ]);
        $this->assertEquals(ApplicationSurvey::STATUS_ASSIGNED, $survey->status);
        $this->assertEquals(Application::STAGE_FIELD_SURVEY_ASSIGNED, $app->fresh()->current_stage);

        // 2. Field Officer schedules visit
        $visitDate = Carbon::tomorrow()->setHour(10);
        $survey = $this->service->scheduleVisit($survey, $fieldOfficer, $visitDate, 'Site visit coordinated with farmer.');
        $this->assertEquals(ApplicationSurvey::STATUS_VISIT_SCHEDULED, $survey->status);
        $this->assertEquals(Application::STAGE_VISIT_SCHEDULED, $app->fresh()->current_stage);

        // 3. Field Officer completes visit
        $survey = $this->service->completeVisit($survey, $fieldOfficer, 'Physical inspection completed at farm location.');
        $this->assertEquals(ApplicationSurvey::STATUS_VISIT_COMPLETED, $survey->status);
        $this->assertEquals(Application::STAGE_VISIT_COMPLETED, $app->fresh()->current_stage);
        $this->assertNotNull($survey->visit_completed_at);

        // 4. Field Officer submits findings
        $survey = $this->service->submitFindings($survey, $fieldOfficer, [
            'land_verified_kanals' => 16.0,
            'water_source_verified' => 'Perennial Irrigation Channel',
            'road_access_verified' => 'Paved Link Road',
            'recommendation' => ApplicationSurvey::REC_RECOMMENDED,
            'findings_summary' => 'Land is fertile, cultivated and suitable for subsidized wheat seed allocation.',
            'gps_coordinates' => '35.9208, 74.3089',
        ]);
        $this->assertEquals(ApplicationSurvey::STATUS_FINDINGS_SUBMITTED, $survey->status);
        $this->assertEquals(Application::STAGE_FINDINGS_SUBMITTED, $app->fresh()->current_stage);
        $this->assertEquals(16.0, $survey->land_verified_kanals);
        $this->assertEquals(ApplicationSurvey::REC_RECOMMENDED, $survey->recommendation);

        // 5. DD alone makes final administrative decision (Approve)
        $app = $this->service->transition($app->fresh(), Application::STAGE_APPROVED, $dd, [
            'note' => 'Approved based on verified field officer survey recommendation.',
        ]);
        $this->assertNotNull($app->verification_note);
    }

    public function test_cost_sharing_scheme_automatically_issues_challan_with_14_day_due_date(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        // 4 bags at 3000 PKR = 12000 PKR
        $app = $this->createTestApplication($gilgit->id, requiresChallan: true, feePerUnit: 3000.00, bags: 4);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        // DD Approves
        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);

        // Transitioned to CHALLAN_ISSUED automatically
        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
        $this->assertNotNull($app->challan_no);
        $this->assertEquals(12000.00, (float) $app->challan_amount);
        $this->assertNotNull($app->challan_issued_at);
        $this->assertEquals(now()->addDays(14)->toDateString(), $app->challan_due_date->toDateString());

        // Approved != Paid != Eligible != Distributed
        $this->assertNull($app->challan_paid_at);
        $this->assertNull($app->payment_verified_at);
        $this->assertNull($app->distributed_at);
    }

    public function test_fee_free_scheme_bypasses_challan_directly_to_eligible_beneficiary(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id, requiresChallan: false);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);

        // Direct bypass to eligible beneficiary
        $this->assertEquals(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
        $this->assertNull($app->challan_no);
        $this->assertNull($app->challan_amount);
    }

    public function test_payment_proof_upload_and_accounts_officer_alone_verifies_payment(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id, requiresChallan: true, feePerUnit: 3000.00, bags: 2);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);

        // Citizen uploads payment receipt
        $citizenUser = $dd; // Actor simulating system recording citizen proof
        $app = $this->service->transition($app, Application::STAGE_PAYMENT_PROOF_SUBMITTED, $citizenUser, [
            'payment_receipt_path' => 'payments/receipt_001.jpg',
        ]);
        $this->assertEquals(Application::STAGE_PAYMENT_PROOF_SUBMITTED, $app->current_stage);
        $this->assertNotNull($app->challan_paid_at);

        // Unauthorized user (e.g. Field Officer) attempts to verify payment -> must be rejected
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();
        try {
            $this->service->transition($app, Application::STAGE_PAYMENT_VERIFIED, $fieldOfficer);
            $this->fail('Field Officer should not be allowed to verify payments.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // Accounts Officer alone verifies payment
        $accountsOfficer = User::where('email', 'accounts.gilgit@alf.gb.gov.pk')->firstOrFail();
        $app = $this->service->transition($app, Application::STAGE_PAYMENT_VERIFIED, $accountsOfficer, [
            'note' => 'Bank scroll scroll_id #9842 verified against Treasury account.',
        ]);
        $this->assertEquals(Application::STAGE_PAYMENT_VERIFIED, $app->current_stage);
        $this->assertEquals($accountsOfficer->id, $app->payment_verified_by);
        $this->assertNotNull($app->payment_verified_at);

        // Automatically advances to eligible beneficiary
        $app = $this->service->transition($app, Application::STAGE_ELIGIBLE_BENEFICIARY, $accountsOfficer);
        $this->assertEquals(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);
    }

    public function test_allocation_and_distribution_flow(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id, requiresChallan: false);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);
        $this->assertEquals(Application::STAGE_ELIGIBLE_BENEFICIARY, $app->current_stage);

        $distOfficer = User::where('email', 'dist.gilgit@alf.gb.gov.pk')->firstOrFail();

        // 1. Stock Allocation (4 bags)
        $app = $this->service->transition($app, Application::STAGE_STOCK_ALLOCATED, $distOfficer, [
            'allocated_quantity' => 4,
        ]);
        $this->assertEquals(Application::STAGE_STOCK_ALLOCATED, $app->current_stage);
        $this->assertEquals(4, $app->allocated_quantity);

        // 2. Partial Distribution (2 bags)
        $app = $this->service->transition($app, Application::STAGE_PARTIALLY_DISTRIBUTED, $distOfficer, [
            'distributed_quantity' => 2,
        ]);
        $this->assertEquals(Application::STAGE_PARTIALLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals(2, $app->distributed_quantity);

        // 3. Full Distribution (4 bags)
        $app = $this->service->transition($app, Application::STAGE_FULLY_DISTRIBUTED, $distOfficer, [
            'distributed_quantity' => 4,
        ]);
        $this->assertEquals(Application::STAGE_FULLY_DISTRIBUTED, $app->current_stage);
        $this->assertEquals(4, $app->distributed_quantity);
        $this->assertNotNull($app->distributed_at);

        // 4. Final Completion
        $app = $this->service->transition($app, Application::STAGE_COMPLETED, $distOfficer);
        $this->assertEquals(Application::STAGE_COMPLETED, $app->current_stage);
    }

    public function test_unpaid_challan_expiration_command_moves_to_dormant(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id, requiresChallan: true);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);

        // Manually age the challan due date to 15 days ago
        $app->update([
            'challan_due_date' => now()->subDays(15)->toDateString(),
        ]);

        $this->artisan('applications:expire-challans')
            ->expectsOutputToContain('Expired 1 unpaid application challan(s)')
            ->assertSuccessful();

        $app->refresh();
        $this->assertEquals(Application::STAGE_DORMANT, $app->current_stage);

        $this->assertDatabaseHas('access_audit_logs', [
            'target_type' => Application::class,
            'target_id' => (string) $app->id,
            'action' => 'challan_expired_to_dormant',
        ]);
    }

    public function test_reactivation_of_dormant_application_requires_documented_dd_decision(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id, requiresChallan: true);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $app = $this->service->transition($app, Application::STAGE_APPROVED, $dd);
        $app->update([
            'current_stage' => Application::STAGE_DORMANT,
            'challan_due_date' => now()->subDays(5)->toDateString(),
        ]);

        // 1. Unauthorized officer cannot reactivate
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();
        try {
            $this->service->reactivateDormant($app, $fieldOfficer, 'Reactivating test.');
            $this->fail('Field Officer should not be allowed to reactivate dormant application.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // 2. Reactivation without documented reason is rejected
        try {
            $this->service->reactivateDormant($app, $dd, '   ');
            $this->fail('Reactivation without reason must be rejected.');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        // 3. DD reactivates with documented administrative reason
        $reason = 'Applicant suffered medical emergency and requested extension; approved under DD discretionary relief.';
        $reactivated = $this->service->reactivateDormant($app, $dd, $reason);

        $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $reactivated->current_stage);
        $this->assertEquals($dd->id, $reactivated->reactivated_by);
        $this->assertEquals($reason, $reactivated->reactivation_reason);
        $this->assertNotNull($reactivated->reactivated_at);
        $this->assertEquals(now()->addDays(14)->toDateString(), $reactivated->challan_due_date->toDateString());
    }

    public function test_cross_district_isolation_strictly_enforced(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        // DD of Diamer attempts to approve Gilgit application
        $diamerDd = User::where('email', 'dd.diamer@alf.gb.gov.pk')->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->service->transition($app, Application::STAGE_APPROVED, $diamerDd);
    }

    public function test_invalid_state_transition_validation_rejects_illegal_jumps(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        // Illegal jump from SUBMITTED directly to COMPLETED or FULLY_DISTRIBUTED
        $this->expectException(ValidationException::class);
        $this->service->transition($app, Application::STAGE_COMPLETED, $dd);
    }

    public function test_optimistic_concurrency_locking_prevents_race_conditions(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();

        // Session 1 reads application at lock_version = 1
        $currentVersion = $app->lock_version;

        // Session 1 performs transition -> version becomes 2
        $this->service->transition($app, Application::STAGE_UNDER_REVIEW, $dd, [], $currentVersion);

        // Session 2 attempts transition providing outdated lock_version = 1 -> conflict
        $this->expectException(RuntimeException::class);
        $this->service->transition($app, Application::STAGE_APPROVED, $dd, [], $currentVersion);
    }

    public function test_sla_ageing_and_clock_calculation(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        // Application received 10 hours ago
        $app->update([
            'stage_updated_at' => now()->subHours(10),
        ]);

        $this->assertEquals(10, $app->stageAgeingHours());
        $this->assertEquals(0, $app->stageAgeingDays());
        $this->assertEquals(48, $app->slaTargetHours());

        $sla = $app->slaStatus();
        $this->assertEquals('normal', $sla['state']);
        $this->assertEquals('info', $sla['badge']);

        // Stage updated 50 hours ago (exceeds 48h SLA for submitted stage)
        $app->update([
            'stage_updated_at' => now()->subHours(50),
        ]);

        $breachedSla = $app->slaStatus();
        $this->assertEquals('breached', $breachedSla['state']);
        $this->assertEquals('danger', $breachedSla['badge']);
    }

    public function test_field_survey_findings_submission_requires_minimum_three_gps_points(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        $survey = $this->service->assignSurvey($app, $dd, $fieldOfficer, [
            'instructions' => 'Conduct physical inspection and map land boundaries.',
        ]);

        // Attempt submission with only 2 GPS points -> must fail validation
        $response = $this->actingAs($fieldOfficer)->post(route('admin.applications.surveys.findings', $survey), [
            'land_verified_kanals' => 10.0,
            'recommendation' => 'recommended',
            'findings_summary' => 'Good land, but only 2 points captured.',
            'gps_points' => json_encode([
                ['lat' => 35.920834, 'lng' => 74.308332, 'accuracy' => 3.2, 'label' => 'Point 1'],
                ['lat' => 35.921510, 'lng' => 74.309015, 'accuracy' => 4.1, 'label' => 'Point 2'],
            ]),
        ]);

        $response->assertSessionHasErrors(['gps_points']);
        $this->assertEquals(ApplicationSurvey::STATUS_ASSIGNED, $survey->fresh()->status);
    }

    public function test_field_survey_findings_submission_accepts_three_or_more_gps_points_and_three_photos_up_to_5mb(): void
    {
        Storage::fake('public');

        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        $survey = $this->service->assignSurvey($app, $dd, $fieldOfficer);

        // Submit 3 GPS points and 3 photos (all <= 5 MB = 5120 KB)
        $photo1 = UploadedFile::fake()->image('land_panorama.jpg', 1200, 800)->size(2048); // 2 MB
        $photo2 = UploadedFile::fake()->image('water_kuhl.jpg', 1200, 800)->size(3072); // 3 MB
        $photo3 = UploadedFile::fake()->image('road_corner.jpg', 1200, 800)->size(4096); // 4 MB

        $gpsPoints = [
            ['lat' => 35.920834, 'lng' => 74.308332, 'accuracy' => 3.2, 'label' => 'Corner 1 - North Gate'],
            ['lat' => 35.921510, 'lng' => 74.309015, 'accuracy' => 4.1, 'label' => 'Corner 2 - Water Channel'],
            ['lat' => 35.920150, 'lng' => 74.309750, 'accuracy' => 2.8, 'label' => 'Corner 3 - South Ridge'],
        ];

        $response = $this->actingAs($fieldOfficer)->post(route('admin.applications.surveys.findings', $survey), [
            'land_verified_kanals' => 12.5,
            'water_source_verified' => 'Perennial Irrigation Channel (Kuhl)',
            'road_access_verified' => 'Paved / Metal Road',
            'recommendation' => 'recommended',
            'findings_summary' => 'Physical on-site survey completed. Perimeter mapped with 3 GPS coordinates and 3 photos attached.',
            'gps_points' => json_encode($gpsPoints),
            'photo_1' => $photo1,
            'photo_2' => $photo2,
            'photo_3' => $photo3,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $survey->refresh();
        $this->assertEquals(ApplicationSurvey::STATUS_FINDINGS_SUBMITTED, $survey->status);
        $this->assertEquals(Application::STAGE_FINDINGS_SUBMITTED, $app->fresh()->current_stage);
        $this->assertEquals(12.5, $survey->land_verified_kanals);
        $this->assertEquals('recommended', $survey->recommendation);

        // Verify 3 GPS points
        $pointsList = $survey->gps_points_list;
        $this->assertCount(3, $pointsList);
        $this->assertEquals(35.920834, $pointsList[0]['lat']);
        $this->assertEquals(74.308332, $pointsList[0]['lng']);
        $this->assertNotNull($survey->google_maps_url);

        // Verify 3 photos stored and reachable
        $photosList = $survey->photos_list;
        $this->assertCount(3, $photosList);
        $this->assertEquals('General Land View', $photosList[0]['label']);
        $this->assertEquals('Irrigation / Water Source', $photosList[1]['label']);
        $this->assertEquals('Road Access / Parcel Corner', $photosList[2]['label']);

        foreach ($photosList as $ph) {
            Storage::disk('public')->assertExists($ph['path']);
        }
    }

    public function test_field_survey_findings_submission_rejects_photos_exceeding_5mb(): void
    {
        Storage::fake('public');

        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        $survey = $this->service->assignSurvey($app, $dd, $fieldOfficer);

        // 6 MB photo (exceeds 5120 KB limit)
        $oversizedPhoto = UploadedFile::fake()->image('oversized.jpg')->size(6144);

        $response = $this->actingAs($fieldOfficer)->post(route('admin.applications.surveys.findings', $survey), [
            'land_verified_kanals' => 10.0,
            'recommendation' => 'recommended',
            'findings_summary' => 'Good land, but photo is too large.',
            'gps_points' => json_encode([
                ['lat' => 35.920834, 'lng' => 74.308332, 'accuracy' => 3.2, 'label' => 'Point 1'],
                ['lat' => 35.921510, 'lng' => 74.309015, 'accuracy' => 4.1, 'label' => 'Point 2'],
                ['lat' => 35.920150, 'lng' => 74.309750, 'accuracy' => 2.8, 'label' => 'Point 3'],
            ]),
            'photo_1' => $oversizedPhoto,
        ]);

        $response->assertSessionHasErrors(['photo_1']);
        $this->assertEquals(ApplicationSurvey::STATUS_ASSIGNED, $survey->fresh()->status);
    }

    public function test_show_view_renders_gps_widget_and_photo_inputs_for_surveyor_and_displays_submitted_evidence(): void
    {
        $gilgit = District::where('name', 'Gilgit')->firstOrFail();
        $app = $this->createTestApplication($gilgit->id);
        $this->service->onSubmission($app);

        $dd = User::where('email', 'dd.gilgit@alf.gb.gov.pk')->firstOrFail();
        $fieldOfficer = User::where('email', 'field.gilgit@alf.gb.gov.pk')->firstOrFail();

        $survey = $this->service->assignSurvey($app, $dd, $fieldOfficer);

        // 1. As Field Officer with active survey task, view should render GPS capture widget and 3 photo inputs
        $response = $this->actingAs($fieldOfficer)->get(route('admin.applications.show', $app));
        $response->assertOk();
        $response->assertSee('Field Survey & Land Verification', false);
        $response->assertSee('GPS Points of the Land (3 or more required)');
        $response->assertSee('Capture Point from Mobile Device GPS');
        $response->assertSee('photo_1');
        $response->assertSee('photo_2');
        $response->assertSee('photo_3');
        $response->assertSee('Max 5MB');

        // 2. Submit survey findings
        $this->service->submitFindings($survey, $fieldOfficer, [
            'land_verified_kanals' => 14.0,
            'water_source_verified' => 'Perennial Irrigation Channel',
            'road_access_verified' => 'Paved / Metal Road',
            'recommendation' => 'recommended',
            'findings_summary' => 'Fertile valley parcel with verified irrigation water and clear boundaries.',
            'gps_points' => [
                ['point_number' => 1, 'lat' => 35.920834, 'lng' => 74.308332, 'accuracy' => 3.2, 'label' => 'North Gate'],
                ['point_number' => 2, 'lat' => 35.921510, 'lng' => 74.309015, 'accuracy' => 4.1, 'label' => 'East Canal'],
                ['point_number' => 3, 'lat' => 35.920150, 'lng' => 74.309750, 'accuracy' => 2.8, 'label' => 'South Ridge'],
            ],
            'photos' => [
                ['path' => 'surveys/test1.jpg', 'url' => '/storage/surveys/test1.jpg', 'label' => 'General Land View', 'size_kb' => 1500],
                ['path' => 'surveys/test2.jpg', 'url' => '/storage/surveys/test2.jpg', 'label' => 'Irrigation / Water Source', 'size_kb' => 2100],
                ['path' => 'surveys/test3.jpg', 'url' => '/storage/surveys/test3.jpg', 'label' => 'Road Access / Parcel Corner', 'size_kb' => 1800],
            ],
        ]);

        // 3. As DD viewing submitted findings, view should render GPS points table, coordinates, and photo cards
        $ddResponse = $this->actingAs($dd)->get(route('admin.applications.show', $app));
        $ddResponse->assertOk();
        $ddResponse->assertSee('FINDINGS SUBMITTED');
        $ddResponse->assertSee('Land Boundary GPS Perimeter (3 Points Recorded)');
        $ddResponse->assertSee('North Gate');
        $ddResponse->assertSee('East Canal');
        $ddResponse->assertSee('South Ridge');
        $ddResponse->assertSee('35.9208340');
        $ddResponse->assertSee('74.3083320');
        $ddResponse->assertSee('Land Inspection Photos (3 / 3 Photos Uploaded, Max 5 MB each)');
        $ddResponse->assertSee('General Land View');
        $ddResponse->assertSee('Irrigation / Water Source');
        $ddResponse->assertSee('Road Access / Parcel Corner');
    }
}

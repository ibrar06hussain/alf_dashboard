<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PdfGenerationOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $fieldOfficer;
    private Scheme $wheatScheme;
    private Application $appWheat;
    private Application $appRegular;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Field Officer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Director Agriculture', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Super Admin Officer',
            'email' => 'admin.pdf@alf.gb.gov.pk',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Admin');

        $dept = Department::create([
            'name' => 'Agriculture Department',
            'slug' => 'agriculture',
            'display_order' => 1,
        ]);

        $district = District::create([
            'name' => 'Gilgit',
            'code' => 'GLT',
            'is_active' => true,
        ]);

        $this->wheatScheme = Scheme::create([
            'department_id' => $dept->id,
            'title' => 'Distribution of Wheat Seed (Cost Sharing 50:50)',
            'slug' => 'distribution-of-wheat-seed-cost-sharing-50-50',
            'status' => 'active',
            'type' => 'running',
            'scope' => 'district',
        ]);

        $wheatForm = Form::create([
            'scheme_id' => $this->wheatScheme->id,
            'title' => 'Wheat Seed Distribution Form',
            'is_published' => true,
            'version' => 1,
        ]);

        $fieldLand = FormField::create([
            'form_id' => $wheatForm->id,
            'field_key' => 'land_kanals',
            'field_label' => 'Available Land (Kanals)',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 1,
        ]);

        $fieldBags = FormField::create([
            'form_id' => $wheatForm->id,
            'field_key' => 'bags_required',
            'field_label' => 'Wheat Seed Bags Required',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 2,
        ]);

        $applicant1 = Applicant::create([
            'full_name' => 'Ali Ahmed Khan',
            'father_name' => 'Muhammad Ahmed',
            'cnic' => '7150112345671',
            'phone' => '03451234567',
            'tehsil' => 'Gilgit',
            'union_council' => 'Danyore',
            'village' => 'Danyore Paeen',
            'district_id' => $district->id,
        ]);

        $this->appWheat = Application::create([
            'application_no' => 'WHEAT-PDF-001',
            'scheme_id' => $this->wheatScheme->id,
            'form_id' => $wheatForm->id,
            'applicant_id' => $applicant1->id,
            'district_id' => $district->id,
            'status' => 'pending',
            'current_stage' => 'submitted',
            'submitted_at' => now(),
        ]);

        ApplicationFieldValue::create([
            'application_id' => $this->appWheat->id,
            'form_field_id' => $fieldLand->id,
            'value' => '16.0',
        ]);

        ApplicationFieldValue::create([
            'application_id' => $this->appWheat->id,
            'form_field_id' => $fieldBags->id,
            'value' => '4',
        ]);

        $regularScheme = Scheme::create([
            'department_id' => $dept->id,
            'title' => 'Farm Machinery Subsidy Program',
            'slug' => 'farm-machinery-subsidy',
            'status' => 'active',
            'type' => 'running',
            'scope' => 'district',
        ]);

        $applicant2 = Applicant::create([
            'full_name' => 'Kareem Ullah',
            'father_name' => 'Rehmat Ullah',
            'cnic' => '7150212345672',
            'phone' => '03459876543',
            'tehsil' => 'Jutial',
            'district_id' => $district->id,
        ]);

        $this->appRegular = Application::create([
            'application_no' => 'AGRI-PDF-002',
            'scheme_id' => $regularScheme->id,
            'applicant_id' => $applicant2->id,
            'district_id' => $district->id,
            'status' => 'district_verified',
            'current_stage' => 'approved',
            'challan_no' => 'CHL-2026-00042',
            'challan_amount' => 7000.00,
            'challan_issued_at' => now(),
            'challan_due_date' => now()->addDays(14)->toDateString(),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Option 1: Wheat Seed MIS Beneficiaries PDF Export.
     */
    public function test_wheat_seed_mis_pdf_export_generates_valid_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.wheat-mis.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(500, strlen($content));
    }

    /**
     * Option 2: Application Review List PDF Export.
     */
    public function test_applications_review_pdf_export_generates_valid_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(500, strlen($content));
    }

    /**
     * Option 3: CM Dashboard Applications PDF Export.
     */
    public function test_cm_dashboard_pdf_export_generates_valid_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.cm-dashboard.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(500, strlen($content));
    }

    /**
     * Option 4A: Bank Challan PDF Download with pre-existing challan.
     */
    public function test_bank_challan_download_with_existing_challan(): void
    {
        $response = $this->get(route('applications.challan.download', ['application_no' => $this->appRegular->application_no]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('Challan-CHL-2026-00042.pdf', (string) $response->headers->get('content-disposition'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
    }

    /**
     * Option 4B: Bank Challan PDF Download when application had no prior challan_no.
     * Should auto-generate challan attributes and return a valid PDF immediately.
     */
    public function test_bank_challan_download_auto_generates_when_missing(): void
    {
        $this->assertNull($this->appWheat->challan_no);

        $response = $this->get(route('applications.challan.download', ['application_no' => $this->appWheat->application_no]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);

        // Verify challan was persisted on application
        $this->appWheat->refresh();
        $this->assertNotNull($this->appWheat->challan_no);
        $this->assertStringStartsWith('CHL-', $this->appWheat->challan_no);
        $this->assertNotNull($this->appWheat->challan_amount);
        $this->assertNotNull($this->appWheat->challan_due_date);
    }
}

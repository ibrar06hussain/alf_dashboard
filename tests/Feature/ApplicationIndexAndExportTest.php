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

class ApplicationIndexAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Scheme $wheatScheme;
    private FormField $fieldLand;
    private FormField $fieldBags;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@alf.gb.gov.pk',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Admin');

        $dept = Department::create(['name' => 'Agriculture', 'slug' => 'agriculture', 'display_order' => 1]);
        $district = District::create(['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);

        $this->wheatScheme = Scheme::create([
            'department_id' => $dept->id,
            'title' => 'Distribution of Wheat Seed (Cost Sharing)',
            'slug' => 'distribution-of-wheat-seed-cost-sharing',
            'status' => 'active',
            'type' => 'running',
            'scope' => 'district',
        ]);

        $form = Form::create([
            'scheme_id' => $this->wheatScheme->id,
            'title' => 'Wheat Seed Form',
            'is_published' => true,
            'version' => 1,
        ]);

        $this->fieldLand = FormField::create([
            'form_id' => $form->id,
            'field_key' => 'land_kanals',
            'field_label' => 'Available Land (Kanals)',
            'field_type' => 'number',
            'is_core' => false,
            'is_mandatory' => true,
            'display_order' => 10,
        ]);

        $this->fieldBags = FormField::create([
            'form_id' => $form->id,
            'field_key' => 'bags_required',
            'field_label' => 'Number of 50kg Seed Bags Required',
            'field_type' => 'number',
            'is_core' => false,
            'is_mandatory' => true,
            'display_order' => 11,
        ]);

        // Create Applicant 1 with 12 Kanals and 3 Bags
        $applicant1 = Applicant::create([
            'cnic' => '7150111111111',
            'full_name' => 'Farmer Twelve Kanals',
            'father_name' => 'Father One',
            'phone' => '03451111111',
            'district_id' => $district->id,
        ]);

        $app1 = Application::create([
            'application_no' => 'ALF-TEST-0001',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $applicant1->id,
            'district_id' => $district->id,
            'form_id' => $form->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app1->id,
            'form_field_id' => $this->fieldLand->id,
            'value' => '12',
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app1->id,
            'form_field_id' => $this->fieldBags->id,
            'value' => '3',
        ]);

        // Create Applicant 2 with 4 Kanals and 1 Bag
        $applicant2 = Applicant::create([
            'cnic' => '7150122222222',
            'full_name' => 'Farmer Four Kanals',
            'father_name' => 'Father Two',
            'phone' => '03452222222',
            'district_id' => $district->id,
        ]);

        $app2 = Application::create([
            'application_no' => 'ALF-TEST-0002',
            'scheme_id' => $this->wheatScheme->id,
            'applicant_id' => $applicant2->id,
            'district_id' => $district->id,
            'form_id' => $form->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app2->id,
            'form_field_id' => $this->fieldLand->id,
            'value' => '4',
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app2->id,
            'form_field_id' => $this->fieldBags->id,
            'value' => '1',
        ]);
    }

    public function test_applications_index_displays_land_and_bags(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.index'));

        $response->assertOk();
        $response->assertSee('Available Land');
        $response->assertSee('Bags Required');
        $response->assertSee('12.0');
        $response->assertSee('3 bags');
        $response->assertSee('4.0');
        $response->assertSee('1 bags');
        $response->assertSee('Export Excel');
        $response->assertSee('Export CSV');
        $response->assertSee('Export PDF');
    }

    public function test_filter_by_min_land(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.index', ['min_land' => 10]));

        $response->assertOk();
        $response->assertSee('ALF-TEST-0001'); // 12 Kanals
        $response->assertDontSee('ALF-TEST-0002'); // 4 Kanals
    }

    public function test_filter_by_min_bags(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.index', ['min_bags' => 2]));

        $response->assertOk();
        $response->assertSee('ALF-TEST-0001'); // 3 bags
        $response->assertDontSee('ALF-TEST-0002'); // 1 bag
    }

    public function test_export_as_excel(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.export', ['format' => 'xlsx']));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));
    }

    public function test_export_as_csv_contains_headers_and_branding(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.export', ['format' => 'csv']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Agriculture Department Gilgit Baltistan — Online Portal', $content);
        $this->assertStringContainsString('Report Generated:', $content);
        $this->assertStringContainsString('Available Land (Kanals)', $content);
        $this->assertStringContainsString('Bags Required (50kg)', $content);
        $this->assertStringContainsString('ALF-TEST-0001', $content);
        $this->assertStringContainsString('12', $content);
        $this->assertStringContainsString('3', $content);
    }

    public function test_export_as_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.applications.export', ['format' => 'pdf']));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $content = $response->getContent();

        // PDF starts with %PDF- header
        $this->assertStringStartsWith('%PDF-', $content);
    }
}

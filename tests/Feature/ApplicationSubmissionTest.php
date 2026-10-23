<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Services\Otp\OtpService;
use App\Support\CoreFormFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Scheme $scheme;

    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Super Admin', 'Secretary', 'Director Agriculture', 'District Officer'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $department = Department::create([
            'name' => 'Agriculture',
            'slug' => 'agriculture',
            'is_active' => true,
        ]);

        $this->district = District::create([
            'name' => 'Gilgit',
            'code' => 'GLT',
            'is_active' => true,
        ]);

        $this->scheme = Scheme::create([
            'department_id' => $department->id,
            'title' => 'Test Seed Scheme',
            'slug' => 'test-seed-scheme',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        $form = Form::create([
            'scheme_id' => $this->scheme->id,
            'title' => 'Test Form',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $field) {
            FormField::create(array_merge($field, ['form_id' => $form->id]));
        }

        FormDocument::create([
            'form_id' => $form->id,
            'document_name' => 'Copy of CNIC',
            'is_mandatory' => true,
            'allowed_extensions' => 'pdf,jpg,jpeg,png',
            'max_size_mb' => 5,
            'display_order' => 1,
        ]);
    }

    public function test_application_does_not_require_otp_verification(): void
    {
        Storage::fake('public');

        $doc = FormDocument::first();

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), array_merge(
            $this->payload(),
            ['documents' => [$doc->id => UploadedFile::fake()->image('cnic.jpg')]]
        ));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_successful_application_submission(): void
    {
        Storage::fake('public');

        $doc = FormDocument::first();

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), array_merge(
            $this->payload(),
            ['documents' => [$doc->id => UploadedFile::fake()->image('cnic.jpg')]]
        ));

        $application = Application::first();
        $this->assertNotNull($application);
        $response->assertRedirect(route('applications.success', $application->application_no));
        $this->assertDatabaseHas('applicants', ['cnic' => '1234567890123']);
    }

    public function test_duplicate_cnic_blocked_for_same_scheme(): void
    {
        Storage::fake('public');

        $applicant = Applicant::create([
            'cnic' => '1234567890123',
            'full_name' => 'Existing',
            'father_name' => 'Father',
            'phone' => '03001112233',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit',
            'land_location' => 'Somewhere',
        ]);

        Application::create([
            'application_no' => 'ALF-TEST-001',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->district->id,
            'form_id' => Form::first()->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $doc = FormDocument::first();
        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), array_merge(
            $this->payload(),
            ['documents' => [$doc->id => UploadedFile::fake()->image('cnic.jpg')]]
        ));

        $response->assertSessionHasErrors('cnic');
        $this->assertEquals(1, Application::count());
    }

    public function test_50_50_wheat_seed_bags_allowed_when_within_1_bag_per_4_kanals(): void
    {
        Storage::fake('public');

        // Configure scheme and form with land_kanals and bags_required
        $form = $this->scheme->forms()->first();
        FormField::create([
            'form_id' => $form->id,
            'field_key' => 'land_kanals',
            'field_label' => 'Land Available (Kanals)',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 10,
        ]);
        FormField::create([
            'form_id' => $form->id,
            'field_key' => 'bags_required',
            'field_label' => 'Number of 50kg Seed Bags Required',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 11,
        ]);

        $doc = FormDocument::first();

        // 40 kanals allows up to 10 bags (40 / 4 = 10)
        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), array_merge(
            $this->payload(),
            [
                'land_kanals' => 40,
                'bags_required' => 10,
                'documents' => [$doc->id => UploadedFile::fake()->image('cnic.jpg')],
            ]
        ));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_50_50_wheat_seed_bags_rejected_when_11_or_greater_for_40_kanals(): void
    {
        Storage::fake('public');

        $form = $this->scheme->forms()->first();
        FormField::create([
            'form_id' => $form->id,
            'field_key' => 'land_kanals',
            'field_label' => 'Land Available (Kanals)',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 10,
        ]);
        FormField::create([
            'form_id' => $form->id,
            'field_key' => 'bags_required',
            'field_label' => 'Number of 50kg Seed Bags Required',
            'field_type' => 'number',
            'is_mandatory' => true,
            'display_order' => 11,
        ]);

        $doc = FormDocument::first();

        // 40 kanals with 11 bags must throw validation error
        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), array_merge(
            $this->payload(),
            [
                'land_kanals' => 40,
                'bags_required' => 11,
                'documents' => [$doc->id => UploadedFile::fake()->image('cnic.jpg')],
            ]
        ));

        $response->assertSessionHasErrors('bags_required');
        $this->assertDatabaseCount('applications', 0);
    }

    private function payload(): array
    {
        return [
            'full_name' => 'Test Farmer',
            'father_name' => 'Test Father',
            'cnic' => '1234567890123',
            'phone' => '03001112233',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Near river',
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\User;
use App\Support\CoreFormFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckboxWithTextTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Scheme $scheme;
    private Form $form;
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Super Admin', 'Secretary', 'Director Agriculture', 'District Officer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@alf.gov.pk',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Admin');

        $dept = Department::create([
            'name' => 'Agriculture Department',
            'slug' => 'agriculture',
            'is_active' => true,
        ]);

        $this->district = District::create([
            'name' => 'Gilgit',
            'name_ur' => 'گلگت',
            'code' => 'GLT',
            'is_active' => true,
        ]);

        $this->scheme = Scheme::create([
            'department_id' => $dept->id,
            'title' => 'Wheat Seed Distribution',
            'slug' => 'wheat-seed-distribution',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        $this->form = Form::create([
            'scheme_id' => $this->scheme->id,
            'title' => 'Application Form for Wheat Seed',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $field) {
            FormField::create(array_merge($field, ['form_id' => $this->form->id]));
        }

        FormDocument::create([
            'form_id' => $this->form->id,
            'document_name' => 'Copy of CNIC',
            'document_name_ur' => 'شناختی کارڈ کی کاپی',
            'is_mandatory' => true,
            'allowed_extensions' => 'pdf,jpg,png',
            'max_size_mb' => 5,
            'display_order' => 1,
        ]);
    }

    public function test_form_builder_supports_checkbox_with_text(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.form-builder.edit', $this->scheme));
        $response->assertOk();
        $response->assertSee('Multiselect Checkbox with Text');
        $response->assertSee('checkbox_with_text');

        // Admin saves a multiselect checkbox_with_text field
        $payload = [
            'title' => 'Form with Multiselect Checkbox and Text',
            'publish' => '1',
            'fields' => [
                [
                    'field_key' => 'existing_loans',
                    'field_label' => 'How much loan do you have from existing sources?',
                    'field_label_ur' => 'موجودہ ذرائع سے آپ پر کتنا قرضہ ہے؟',
                    'field_type' => 'checkbox_with_text',
                    'is_mandatory' => '0',
                    'placeholder' => 'Enter loan amount / details',
                    'placeholder_ur' => 'قرضے کی رقم یا تفصیلات درج کریں',
                    'options' => "Commercial Bank, ZTBL, Microfinance, Informal / Other",
                    'display_order' => 10,
                ],
            ],
            'documents' => [
                [
                    'document_name' => 'CNIC Copy',
                    'document_name_ur' => 'شناختی کارڈ',
                    'allowed_extensions' => 'pdf,jpg',
                    'max_size_mb' => 5,
                    'is_mandatory' => '1',
                    'display_order' => 1,
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->admin)->put(route('admin.form-builder.update', $this->scheme), $payload);
        $updateResponse->assertRedirect(route('admin.form-builder.edit', $this->scheme));

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $this->form->id,
            'field_key' => 'existing_loans',
            'field_type' => 'checkbox_with_text',
            'field_label' => 'How much loan do you have from existing sources?',
        ]);

        $field = FormField::where('form_id', $this->form->id)->where('field_key', 'existing_loans')->first();
        $this->assertEquals(['Commercial Bank', 'ZTBL', 'Microfinance', 'Informal / Other'], $field->options_json);
    }

    public function test_apply_page_renders_multiselect_checkboxes_with_individual_text_fields(): void
    {
        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'loan_sources',
            'field_label' => 'Existing Loans Breakdown',
            'field_label_ur' => 'قرضوں کی تفصیلات',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => false,
            'placeholder' => 'Enter amount in PKR',
            'placeholder_ur' => 'رقم درج کریں',
            'options_json' => ['Commercial Bank', 'ZTBL', 'Microfinance'],
            'display_order' => 15,
        ]);

        $response = $this->get(route('schemes.apply', $this->scheme->slug));
        $response->assertOk();
        $response->assertSee('Existing Loans Breakdown');
        $response->assertSee('قرضوں کی تفصیلات');
        $response->assertSee('Commercial Bank');
        $response->assertSee('ZTBL');
        $response->assertSee('Microfinance');
        $response->assertSee('field_loan_sources_0_check');
        $response->assertSee('field_loan_sources_0_text_input');
        $response->assertSee('field_loan_sources_1_check');
        $response->assertSee('field_loan_sources_1_text_input');
        $response->assertSee('field_loan_sources_2_check');
        $response->assertSee('field_loan_sources_2_text_input');
    }

    public function test_application_submission_with_multiselect_checkboxes_and_text_fields(): void
    {
        Storage::fake('public');

        $field = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'financial_loans',
            'field_label' => 'Financial Loans',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => false,
            'options_json' => ['Commercial Bank', 'ZTBL', 'Microfinance'],
            'display_order' => 11,
        ]);

        $doc = FormDocument::first();

        // User checks Commercial Bank and Microfinance, leaving ZTBL unchecked
        $payload = [
            'full_name' => 'Ali Khan',
            'father_name' => 'Hassan Khan',
            'cnic' => '7110112345671',
            'phone' => '03451234567',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Oshikhandass, Gilgit',
            'financial_loans' => [
                0 => [
                    'label' => 'Commercial Bank',
                    'checked' => '1',
                    'text' => '500,000 PKR from Bank of Khyber',
                ],
                1 => [
                    'label' => 'ZTBL',
                    // unchecked
                ],
                2 => [
                    'label' => 'Microfinance',
                    'checked' => '1',
                    'text' => '150,000 PKR from Akhuwat',
                ],
            ],
            'documents' => [
                $doc->id => UploadedFile::fake()->create('cnic.pdf', 100, 'application/pdf'),
            ],
        ];

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);
        $response->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7110112345671'))->first();
        $this->assertNotNull($app);

        $this->assertDatabaseHas('application_field_values', [
            'application_id' => $app->id,
            'form_field_id' => $field->id,
            'value' => 'Commercial Bank: 500,000 PKR from Bank of Khyber; Microfinance: 150,000 PKR from Akhuwat',
        ]);
    }

    public function test_application_submission_with_checkbox_unchecked(): void
    {
        Storage::fake('public');

        $field = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'prior_subsidy',
            'field_label' => 'Received Prior Government Subsidy',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => false,
            'display_order' => 12,
        ]);

        $doc = FormDocument::first();

        $payload = [
            'full_name' => 'Fatima Bibi',
            'father_name' => 'Muhammad Ali',
            'cnic' => '7110112345672',
            'phone' => '03451234568',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Danyore, Gilgit',
            'documents' => [
                $doc->id => UploadedFile::fake()->create('cnic.pdf', 100, 'application/pdf'),
            ],
        ];

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);
        $response->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7110112345672'))->first();
        $this->assertNotNull($app);

        $this->assertDatabaseHas('application_field_values', [
            'application_id' => $app->id,
            'form_field_id' => $field->id,
            'value' => null,
        ]);
    }

    public function test_validation_fails_when_selected_multiselect_checkbox_has_no_text(): void
    {
        Storage::fake('public');

        FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'crop_details',
            'field_label' => 'Select Crops and Acreage',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => false,
            'options_json' => ['Wheat', 'Maize'],
            'display_order' => 13,
        ]);

        $doc = FormDocument::first();

        $payload = [
            'full_name' => 'Karim Shah',
            'father_name' => 'Shah Khan',
            'cnic' => '7110112345673',
            'phone' => '03451234569',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Nomal, Gilgit',
            'crop_details' => [
                0 => [
                    'label' => 'Wheat',
                    'checked' => '1',
                    'text' => '', // checked but text is empty!
                ],
            ],
            'documents' => [
                $doc->id => UploadedFile::fake()->create('cnic.pdf', 100, 'application/pdf'),
            ],
        ];

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);
        $response->assertSessionHasErrors('crop_details.0.text');
    }

    public function test_admin_show_view_displays_multiselect_checkbox_with_text_values(): void
    {
        $field = FormField::create([
            'form_id' => $this->form->id,
            'field_key' => 'existing_liabilities',
            'field_label' => 'Existing Liabilities',
            'field_type' => 'checkbox_with_text',
            'is_mandatory' => false,
            'display_order' => 14,
        ]);

        $applicant = Applicant::create([
            'cnic' => '7110112345674',
            'full_name' => 'Shahid Hussain',
            'father_name' => 'Ghulam Hussain',
            'phone' => '03451234570',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'land_location' => 'Jutial, Gilgit',
        ]);

        $app = Application::create([
            'application_no' => 'APP-2026-9999',
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->district->id,
            'form_id' => $this->form->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $field->id,
            'value' => 'Commercial Bank: 400,000 PKR; Microfinance: 80,000 PKR',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.applications.show', $app));
        $response->assertOk();
        $response->assertSee('Existing Liabilities');
        $response->assertSee('Commercial Bank');
        $response->assertSee('400,000 PKR');
        $response->assertSee('Microfinance');
        $response->assertSee('80,000 PKR');
        $response->assertSee('Selected');
    }
}

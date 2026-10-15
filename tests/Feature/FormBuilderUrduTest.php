<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\User;
use App\Support\CoreFormFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormBuilderUrduTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Scheme $scheme;
    private Form $form;

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

        District::create([
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

        foreach (CoreFormFields::definitions() as $def) {
            FormField::create(array_merge($def, ['form_id' => $this->form->id]));
        }

        FormDocument::create([
            'form_id' => $this->form->id,
            'document_name' => 'Copy of CNIC',
            'document_name_ur' => 'شناختی کارڈ کی کاپی',
            'is_mandatory' => true,
            'allowed_extensions' => 'pdf,jpg,jpeg,png',
            'max_size_mb' => 5,
            'display_order' => 1,
        ]);
    }

    public function test_admin_can_access_form_builder_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.form-builder.index'));
        $response->assertOk();
        $response->assertSee('Application Forms');
        $response->assertSee('Wheat Seed Distribution');
        $response->assertSee('Edit Form');

        // Test alias route as well
        $aliasResponse = $this->actingAs($this->admin)->get('/admin/application-forms');
        $aliasResponse->assertOk();
    }

    public function test_admin_can_view_form_builder_edit_with_urdu_inputs(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.form-builder.edit', $this->scheme));
        $response->assertOk();
        $response->assertSee('Form Builder');
        $response->assertSee('پورا نام');
        $response->assertSee('شناختی کارڈ کی کاپی');
        $response->assertSee('field_label_ur');
        $response->assertSee('document_name_ur');
    }

    public function test_admin_can_update_form_with_custom_urdu_translations(): void
    {
        $payload = [
            'title' => 'Updated Wheat Seed Form',
            'publish' => '1',
            'fields' => [
                [
                    'field_key' => 'full_name',
                    'field_label' => 'Full Name',
                    'field_label_ur' => 'مکمل نام درخواست دہندہ',
                    'placeholder_ur' => 'اپنا مکمل شناختی کارڈ والا نام لکھیں',
                    'field_type' => 'text',
                    'is_mandatory' => '1',
                    'display_order' => 1,
                ],
                [
                    'field_key' => 'farm_experience',
                    'field_label' => 'Farming Experience (Years)',
                    'field_label_ur' => 'کاشتکاری کا تجربہ (سال)',
                    'placeholder_ur' => 'مثلاً 5 سال',
                    'field_type' => 'number',
                    'is_mandatory' => '1',
                    'display_order' => 10,
                ],
            ],
            'documents' => [
                [
                    'document_name' => 'Land Revenue Record',
                    'document_name_ur' => 'فرد ملکیت یا زرعی ریکارڈ',
                    'allowed_extensions' => 'pdf,jpg,png',
                    'max_size_mb' => 5,
                    'is_mandatory' => '1',
                    'display_order' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->put(route('admin.form-builder.update', $this->scheme), $payload);
        $response->assertRedirect(route('admin.form-builder.edit', $this->scheme));

        $this->assertDatabaseHas('forms', [
            'id' => $this->form->id,
            'title' => 'Updated Wheat Seed Form',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $this->form->id,
            'field_key' => 'full_name',
            'field_label_ur' => 'مکمل نام درخواست دہندہ',
            'placeholder_ur' => 'اپنا مکمل شناختی کارڈ والا نام لکھیں',
        ]);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $this->form->id,
            'field_key' => 'farm_experience',
            'field_label_ur' => 'کاشتکاری کا تجربہ (سال)',
            'placeholder_ur' => 'مثلاً 5 سال',
        ]);

        $this->assertDatabaseHas('form_documents', [
            'form_id' => $this->form->id,
            'document_name' => 'Land Revenue Record',
            'document_name_ur' => 'فرد ملکیت یا زرعی ریکارڈ',
        ]);
    }

    public function test_public_apply_form_displays_parallel_english_and_urdu_labels(): void
    {
        $response = $this->get(route('schemes.apply', $this->scheme->slug));
        $response->assertOk();

        // English labels
        $response->assertSee('Full Name');
        $response->assertSee('CNIC Number');
        $response->assertSee('District');
        $response->assertSee('Copy of CNIC');

        // Urdu parallel labels
        $response->assertSee('پورا نام');
        $response->assertSee('شناختی کارڈ نمبر');
        $response->assertSee('ضلع');
        $response->assertSee('شناختی کارڈ کی کاپی');

        // Bilingual classes
        $response->assertSee('field-label-wrapper');
        $response->assertSee('field-label-en');
        $response->assertSee('field-label-ur');
    }
}

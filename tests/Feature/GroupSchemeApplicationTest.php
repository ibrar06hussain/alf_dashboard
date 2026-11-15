<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationMember;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\User;
use App\Support\CoreFormFields;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupSchemeApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected District $gilgit;
    protected Department $agriculture;
    protected Scheme $groupScheme;
    protected Form $groupForm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->gilgit = District::firstOrCreate(['id' => 1], ['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);
        $this->agriculture = Department::firstOrCreate(
            ['id' => 1],
            ['name' => 'Agriculture Department', 'slug' => 'agriculture', 'is_active' => true]
        );

        $this->superAdmin = User::factory()->create([
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->groupScheme = Scheme::create([
            'department_id' => $this->agriculture->id,
            'title' => 'Farmer Cooperative Solar Tube-Well Scheme',
            'title_ur' => 'کسان گروپ سولر ٹیوب ویل اسکیم',
            'slug' => 'farmer-cooperative-solar-tubewell',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => false,
        ]);

        $this->groupForm = Form::create([
            'scheme_id' => $this->groupScheme->id,
            'title' => 'Cooperative Group Application Form',
            'version' => 1,
            'is_published' => true,
            'is_group_application' => true,
            'min_group_members' => 2,
            'max_group_members' => 5,
            'group_section_title' => 'Cooperative Group Members Details',
            'group_section_title_ur' => 'کوآپریٹو گروپ ممبران کی تفصیلات',
            'member_custom_fields_json' => [
                [
                    'key' => 'group_share',
                    'label' => 'Water Share (%)',
                    'label_ur' => 'پانی میں حصہ (فیصد)',
                    'type' => 'number',
                    'options' => [],
                    'required' => true,
                ],
            ],
        ]);

        foreach (CoreFormFields::definitions() as $field) {
            FormField::create(array_merge($field, ['form_id' => $this->groupForm->id]));
        }
    }

    public function test_admin_can_configure_bilingual_group_form_settings_in_form_builder(): void
    {
        $fieldsPayload = $this->groupForm->fields->map(fn (FormField $f, int $idx) => [
            'id' => $f->id,
            'field_key' => $f->field_key,
            'field_label' => $f->field_label,
            'field_label_ur' => $f->field_label_ur,
            'field_type' => $f->field_type,
            'is_mandatory' => $f->is_mandatory ? '1' : '0',
            'display_order' => $idx + 1,
        ])->all();

        $response = $this->actingAs($this->superAdmin)
            ->put(route('admin.form-builder.update', $this->groupScheme), [
                'title' => 'Updated Group Form Title',
                'is_group_application' => '1',
                'min_group_members' => 2,
                'max_group_members' => 6,
                'group_section_title' => 'Cluster Group Members',
                'group_section_title_ur' => 'کلسٹر گروپ ممبران',
                'member_custom_fields' => [
                    [
                        'key' => 'member_designation',
                        'label' => 'Designation in Group',
                        'label_ur' => 'گروپ میں عہدہ',
                        'type' => 'text',
                        'required' => '1',
                    ],
                ],
                'fields' => $fieldsPayload,
                'publish' => '1',
            ]);

        $response->assertRedirect(route('admin.form-builder.edit', $this->groupScheme));

        $this->groupForm->refresh();
        $this->assertTrue($this->groupForm->is_group_application);
        $this->assertSame(2, $this->groupForm->min_group_members);
        $this->assertSame(6, $this->groupForm->max_group_members);
        $this->assertSame('Cluster Group Members', $this->groupForm->group_section_title);
        $this->assertSame('کلسٹر گروپ ممبران', $this->groupForm->group_section_title_ur);
        $this->assertSame('member_designation', $this->groupForm->member_custom_fields_json[0]['key']);
        $this->assertSame('گروپ میں عہدہ', $this->groupForm->member_custom_fields_json[0]['label_ur']);
    }

    public function test_public_apply_page_renders_bilingual_group_leader_and_member_fields(): void
    {
        $response = $this->get(route('schemes.apply', $this->groupScheme->slug));

        $response->assertOk()
            ->assertSee('Group Leader / Primary Applicant Details')
            ->assertSee('گروپ لیڈر (سرکردہ درخواست گزار) کی تفصیلات')
            ->assertSee('Cooperative Group Members Details')
            ->assertSee('کوآپریٹو گروپ ممبران کی تفصیلات')
            ->assertSee('Water Share (%)')
            ->assertSee('پانی میں حصہ (فیصد)');
    }

    public function test_group_application_rejects_duplicate_cnic_or_duplicate_mobile_between_leader_and_members(): void
    {
        // Case 1: Member #1 shares CNIC with Group Leader, Member #2 shares Mobile with Member #1
        $response = $this->post(route('schemes.apply.store', $this->groupScheme->slug), [
            'full_name' => 'Leader Karim Khan',
            'father_name' => 'Sultan Khan',
            'cnic' => '71101-1111111-1',
            'phone' => '0312-1111111',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'village' => 'Jutial',
            'land_location' => 'Plot 12 Jutial',
            'members' => [
                [
                    'full_name' => 'Member One Duplicate Leader CNIC',
                    'father_name' => 'Father One',
                    'cnic' => '71101-1111111-1', // Same as Group Leader!
                    'phone' => '0345-2222222',
                    'village' => 'Jutial',
                    'land_kanals' => 5,
                    'extra' => ['group_share' => 30],
                ],
                [
                    'full_name' => 'Member Two Duplicate Member Phone',
                    'father_name' => 'Father Two',
                    'cnic' => '71101-3333333-3',
                    'phone' => '923452222222', // Same phone as Member #1 in 923XX format!
                    'village' => 'Danyore',
                    'land_kanals' => 6,
                    'extra' => ['group_share' => 30],
                ],
            ],
        ]);

        $response->assertSessionHasErrors([
            'members.0.cnic',
            'members.1.phone',
        ]);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('application_members', 0);
    }

    public function test_group_application_succeeds_with_unique_leader_and_members_and_displays_in_admin_dossier(): void
    {
        $response = $this->post(route('schemes.apply.store', $this->groupScheme->slug), [
            'full_name' => 'Leader Karim Khan',
            'father_name' => 'Sultan Khan',
            'cnic' => '71101-1111111-1',
            'phone' => '0312-1111111',
            'district_id' => $this->gilgit->id,
            'tehsil' => 'Gilgit',
            'village' => 'Jutial',
            'land_location' => 'Plot 12 Jutial',
            'members' => [
                [
                    'full_name' => 'Member Salman Ali',
                    'father_name' => 'Barkat Ali',
                    'cnic' => '71101-2222222-2',
                    'phone' => '0345-2222222',
                    'village' => 'Jutial',
                    'land_kanals' => 5,
                    'extra' => ['group_share' => 35],
                ],
                [
                    'full_name' => 'Member Nasir Hussain',
                    'father_name' => 'Fida Hussain',
                    'cnic' => '71101-3333333-3',
                    'phone' => '0355-3333333',
                    'village' => 'Danyore',
                    'land_kanals' => 8,
                    'extra' => ['group_share' => 25],
                ],
            ],
        ]);

        $application = Application::first();
        $this->assertNotNull($application);
        $response->assertRedirect(route('applications.success', $application->application_no));

        $this->assertDatabaseCount('application_members', 2);
        $this->assertDatabaseHas('application_members', [
            'application_id' => $application->id,
            'member_order' => 1,
            'full_name' => 'Member Salman Ali',
            'cnic' => '7110122222222',
            'phone' => '03452222222',
        ]);
        $this->assertDatabaseHas('application_members', [
            'application_id' => $application->id,
            'member_order' => 2,
            'full_name' => 'Member Nasir Hussain',
            'cnic' => '7110133333333',
            'phone' => '03553333333',
        ]);

        // Verify Admin Application Dossier displays Group Leader and Group Members
        $adminShow = $this->actingAs($this->superAdmin)
            ->get(route('admin.applications.show', $application));

        $adminShow->assertOk()
            ->assertSee('Group Leader / Primary Applicant')
            ->assertSee('1 Leader + 2 Member(s) = 3 Total')
            ->assertSee('Member Salman Ali')
            ->assertSee('7110122222222')
            ->assertSee('Member Nasir Hussain')
            ->assertSee('7110133333333')
            ->assertSee('Water Share (%)');
    }
}


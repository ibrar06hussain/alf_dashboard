<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\Form;
use App\Models\FormDocument;
use App\Models\FormField;
use App\Models\Scheme;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Support\CoreFormFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CascadingLocationTest extends TestCase
{
    use RefreshDatabase;

    private District $district;
    private Tehsil $tehsilWithUc;
    private Tehsil $tehsilNoUc;
    private UnionCouncil $uc1;
    private UnionCouncil $uc2;
    private Scheme $scheme;
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Super Admin', 'Secretary', 'Director Agriculture', 'District Officer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
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

        $this->tehsilWithUc = Tehsil::create([
            'district_id' => $this->district->id,
            'name' => 'Gilgit City',
        ]);

        $this->tehsilNoUc = Tehsil::create([
            'district_id' => $this->district->id,
            'name' => 'Aliabad',
        ]);

        $this->uc2 = UnionCouncil::create([
            'tehsil_id' => $this->tehsilWithUc->id,
            'name' => 'Zulfiqarabad',
        ]);

        $this->uc1 = UnionCouncil::create([
            'tehsil_id' => $this->tehsilWithUc->id,
            'name' => 'Amphary',
        ]);

        $this->scheme = Scheme::create([
            'department_id' => $department->id,
            'title' => 'Cascading Test Scheme',
            'slug' => 'cascading-test-scheme',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'last_date' => now()->addMonth(),
        ]);

        $this->form = Form::create([
            'scheme_id' => $this->scheme->id,
            'title' => 'Cascading Form',
            'version' => 1,
            'is_published' => true,
        ]);

        foreach (CoreFormFields::definitions() as $field) {
            FormField::create(array_merge($field, ['form_id' => $this->form->id]));
        }

        FormDocument::create([
            'form_id' => $this->form->id,
            'document_name' => 'CNIC Copy',
            'is_mandatory' => false,
            'allowed_extensions' => 'pdf,jpg,png',
            'max_size_mb' => 2,
            'display_order' => 1,
        ]);
    }

    public function test_tehsils_endpoint_returns_alphabetically_sorted_tehsils(): void
    {
        $response = $this->get(route('locations.tehsils', $this->district->id));

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertEquals('Aliabad', $data[0]['name']);
        $this->assertEquals('Gilgit City', $data[1]['name']);
    }

    public function test_union_councils_endpoint_returns_alphabetically_sorted_ucs(): void
    {
        $response = $this->get(route('locations.union-councils', $this->tehsilWithUc->id));

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertEquals('Amphary', $data[0]['name']);
        $this->assertEquals('Zulfiqarabad', $data[1]['name']);
    }

    public function test_union_councils_endpoint_returns_empty_state_for_tehsil_without_ucs(): void
    {
        $response = $this->get(route('locations.union-councils', $this->tehsilNoUc->id));

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(0, $data);
        $this->assertEquals([], $data);
    }

    public function test_application_submission_succeeds_with_cascading_selections(): void
    {
        $payload = [
            'full_name' => 'Ali Khan',
            'father_name' => 'Khan',
            'cnic' => '7150112345671',
            'phone' => '03001234567',
            'district_id' => $this->district->id,
            'tehsil' => $this->tehsilWithUc->name,
            'union_council' => $this->uc1->name,
            'village' => 'Oshikhandass',
            'land_location' => 'Main Road Gilgit',
        ];

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applicants', [
            'cnic' => '7150112345671',
            'district_id' => $this->district->id,
            'tehsil' => 'Gilgit City',
            'union_council' => 'Amphary',
            'village' => 'Oshikhandass',
        ]);
    }

    public function test_application_submission_succeeds_without_union_council_when_tehsil_has_none(): void
    {
        $payload = [
            'full_name' => 'Hunza Farmer',
            'father_name' => 'Father',
            'cnic' => '7150198765431',
            'phone' => '03007654321',
            'district_id' => $this->district->id,
            'tehsil' => $this->tehsilNoUc->name,
            'union_council' => null,
            'village' => null,
            'land_location' => 'Aliabad Center',
        ];

        $response = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applicants', [
            'cnic' => '7150198765431',
            'district_id' => $this->district->id,
            'tehsil' => 'Aliabad',
            'union_council' => null,
            'village' => null,
        ]);
    }

    public function test_apply_view_renders_village_field_after_union_council(): void
    {
        $response = $this->get(route('schemes.apply', $this->scheme->slug));

        $response->assertOk();
        $response->assertSee('name="village"', false);
        $response->assertSee('id="field_village"', false);
        $content = $response->getContent();
        $ucPos = strpos($content, 'name="union_council"');
        $villagePos = strpos($content, 'name="village"');
        $this->assertNotFalse($ucPos);
        $this->assertNotFalse($villagePos);
        $this->assertTrue($villagePos > $ucPos, 'Village field should appear after union_council field.');
    }

    public function test_success_and_tracking_view_display_locations_and_village(): void
    {
        $payload = [
            'full_name' => 'Kareem Ullah',
            'father_name' => 'Ullah',
            'cnic' => '7150155555551',
            'phone' => '03459998877',
            'district_id' => $this->district->id,
            'tehsil' => $this->tehsilWithUc->name,
            'union_council' => $this->uc1->name,
            'village' => 'Oshikhandass Bala',
            'land_location' => 'Sector 4, Gilgit',
        ];

        $postResp = $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);
        $postResp->assertSessionHasNoErrors();

        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150155555551'))->firstOrFail();

        // Test Success View
        $successResp = $this->get(route('applications.success', $app->application_no));
        $successResp->assertOk();
        $successResp->assertSee($app->application_no);
        $successResp->assertSee('Gilgit City');
        $successResp->assertSee('Amphary');
        $successResp->assertSee('Oshikhandass Bala');

        // Test Tracking View
        $trackResp = $this->get(route('applications.track', [
            'application_no' => $app->application_no,
            'cnic' => '7150155555551',
        ]));
        $trackResp->assertOk();
        $trackResp->assertSee($app->application_no);
        $trackResp->assertSee('Gilgit City');
        $trackResp->assertSee('Amphary');
        $trackResp->assertSee('Oshikhandass Bala');
    }

    public function test_admin_review_displays_and_searches_by_location_and_village(): void
    {
        $payload = [
            'full_name' => 'Shahid Amin',
            'father_name' => 'Amin',
            'cnic' => '7150166666661',
            'phone' => '03456666666',
            'district_id' => $this->district->id,
            'tehsil' => $this->tehsilWithUc->name,
            'union_council' => $this->uc2->name,
            'village' => 'Danyore Payeen',
            'land_location' => 'Main Bazaar',
        ];

        $this->post(route('schemes.apply.store', $this->scheme->slug), $payload);
        $app = Application::whereHas('applicant', fn ($q) => $q->where('cnic', '7150166666661'))->firstOrFail();

        $admin = User::create([
            'name' => 'Super Admin Test',
            'email' => 'admin_test@alf.gov.pk',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Admin');

        // Test Admin Show View
        $showResp = $this->actingAs($admin)->get(route('admin.applications.show', $app));
        $showResp->assertOk();
        $showResp->assertSee('Gilgit City');
        $showResp->assertSee('Zulfiqarabad');
        $showResp->assertSee('Danyore Payeen');

        // Test Admin Search by Village
        $searchResp = $this->actingAs($admin)->get(route('admin.applications.index', ['q' => 'Danyore Payeen']));
        $searchResp->assertOk();
        $searchResp->assertSee($app->application_no);

        // Test Admin Search by Union Council
        $searchUcResp = $this->actingAs($admin)->get(route('admin.applications.index', ['q' => 'Zulfiqarabad']));
        $searchUcResp->assertOk();
        $searchUcResp->assertSee($app->application_no);
    }
}

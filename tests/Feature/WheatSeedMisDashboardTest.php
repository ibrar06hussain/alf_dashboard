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
use App\Models\SchemeDistrict;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\WheatSeedMisService;
use App\Support\Cnic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WheatSeedMisDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $directorAgri;
    private User $districtOfficer;
    private Scheme $scheme;
    private District $districtGilgit;
    private District $districtSkardu;
    private FormField $fieldLand;
    private FormField $fieldRoad;
    private FormField $fieldWater;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $roles = [
            'Super Admin',
            'Secretary',
            'Director Agriculture',
            'Director Livestock',
            'Director Fisheries',
            'District Officer',
        ];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // Departments
        $deptAgri = Department::create(['name' => 'Agriculture', 'slug' => 'agriculture', 'display_order' => 1]);

        // Districts
        $this->districtGilgit = District::create(['name' => 'Gilgit', 'name_ur' => 'گلگت', 'code' => 'GLT', 'is_active' => true]);
        $this->districtSkardu = District::create(['name' => 'Skardu', 'name_ur' => 'سکردو', 'code' => 'SKD', 'is_active' => true]);

        // Wheat Seed Scheme
        $this->scheme = Scheme::create([
            'id' => 1,
            'department_id' => $deptAgri->id,
            'title' => 'Distribution of Wheat Seed (Cost Sharing)',
            'title_ur' => 'گندم کے بیج کی تقسیم',
            'slug' => 'distribution-of-wheat-seed-cost-sharing',
            'scope' => 'district',
            'status' => 'active',
        ]);

        // Quotas
        SchemeDistrict::create(['scheme_id' => $this->scheme->id, 'district_id' => $this->districtGilgit->id, 'quota' => 50]);
        SchemeDistrict::create(['scheme_id' => $this->scheme->id, 'district_id' => $this->districtSkardu->id, 'quota' => 50]);

        // Form & Fields
        $form = Form::create([
            'scheme_id' => $this->scheme->id,
            'title' => 'Distribution of Wheat Seed Application',
            'is_published' => true,
        ]);
        $this->fieldLand = FormField::create(['form_id' => $form->id, 'field_key' => 'land_kanals', 'field_label' => 'Land Available (Kanals)', 'field_type' => 'number', 'display_order' => 1]);
        $this->fieldRoad = FormField::create(['form_id' => $form->id, 'field_key' => 'road_access', 'field_label' => 'Road Access to Land', 'field_type' => 'radio', 'options_json' => ['Yes', 'No'], 'display_order' => 2]);
        $this->fieldWater = FormField::create(['form_id' => $form->id, 'field_key' => 'water_available', 'field_label' => 'Availability of Water', 'field_type' => 'radio', 'options_json' => ['Yes', 'No'], 'display_order' => 3]);

        // Users
        $this->superAdmin = User::factory()->create(['name' => 'Super Admin User', 'is_active' => true]);
        $this->superAdmin->assignRole('Super Admin');

        $this->directorAgri = User::factory()->create([
            'name' => 'Director Agri User',
            'department_id' => $deptAgri->id,
            'is_active' => true,
        ]);
        $this->directorAgri->assignRole('Director Agriculture');

        $this->districtOfficer = User::factory()->create([
            'name' => 'District Officer Gilgit',
            'district_id' => $this->districtGilgit->id,
            'is_active' => true,
        ]);
        $this->districtOfficer->assignRole('District Officer');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.wheat-mis.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_is_forbidden(): void
    {
        $randomUser = User::factory()->create(['is_active' => true]);
        $response = $this->actingAs($randomUser)->get(route('admin.wheat-mis.index'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_wheat_seed_mis_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.index'));

        $response->assertStatus(200)
            ->assertSee('Distribution of Wheat Seed')
            ->assertSee('Scheme MIS')
            ->assertSee('Available Wheat Land')
            ->assertSee('District Demand-Supply')
            ->assertSee('Data Dictionary');
    }

    public function test_district_officer_is_scoped_to_assigned_district(): void
    {
        // Gilgit Application (16 kanals = 2 acres = 4 bags)
        $appGilgit = $this->createWheatApplication($this->districtGilgit, '7150112345671', '03001234567', 16, 'directorate_approved');

        // Skardu Application (8 kanals = 1 acre = 2 bags)
        $appSkardu = $this->createWheatApplication($this->districtSkardu, '7140112345672', '03009876543', 8, 'directorate_approved');

        $response = $this->actingAs($this->districtOfficer)->get(route('admin.wheat-mis.index'));

        $response->assertStatus(200)
            ->assertSee($appGilgit->application_no)
            ->assertDontSee($appSkardu->application_no);
    }

    public function test_kpi_calculations_for_land_bags_weights_and_unconfigured_price(): void
    {
        // Application 1: 16 Kanals (= 2 Acres, 4 bags, 200 kg = 0.2 MT)
        $this->createWheatApplication($this->districtGilgit, '7150111111111', '03001111111', 16, 'directorate_approved');

        // Application 2: 8 Kanals (= 1 Acre, 2 bags, 100 kg = 0.1 MT)
        $this->createWheatApplication($this->districtSkardu, '7140122222222', '03002222222', 8, 'disbursed');

        $service = app(WheatSeedMisService::class);
        $kpis = $service->getKpis();

        $this->assertSame(2, $kpis['total_applications']);
        $this->assertEquals(24.0, $kpis['land']['available_kanals']);
        $this->assertEquals(3.0, $kpis['land']['available_acres']); // 24 / 8 = 3.0 Acres

        // Bags
        $this->assertEquals(6, $kpis['bags']['requested']); // 4 + 2
        $this->assertEquals(6, $kpis['bags']['approved']); // both approved/disbursed
        $this->assertEquals(2, $kpis['bags']['disbursed']);

        // Weights
        $this->assertEquals(300, $kpis['weights']['approved_kg']); // 6 bags * 50kg = 300kg
        $this->assertEquals(0.3, $kpis['weights']['approved_mt']); // 300 / 1000 = 0.3 MT

        // Cost-sharing without rate configuration
        $this->assertFalse($kpis['cost_sharing']['is_configured']);
        $this->assertNull($kpis['cost_sharing']['rate_per_bag']);
    }

    public function test_cost_sharing_50_50_with_configured_rate(): void
    {
        // Configure approved rate in SiteSetting (e.g. PKR 6,000 per 50kg bag)
        SiteSetting::create([
            'key' => 'wheat_seed_bag_rate',
            'label' => 'Approved Wheat Seed Bag Rate',
            'value' => '6000',
            'group' => 'schemes',
        ]);

        // Application: 8 Kanals = 2 bags approved
        $this->createWheatApplication($this->districtGilgit, '7150133333333', '03003333333', 8, 'directorate_approved');

        $service = app(WheatSeedMisService::class);
        $kpis = $service->getKpis();

        $this->assertTrue($kpis['cost_sharing']['is_configured']);
        $this->assertEquals(6000.0, $kpis['cost_sharing']['rate_per_bag']);
        $this->assertEquals(12000.0, $kpis['cost_sharing']['total_approved_value']); // 2 bags * 6000
        $this->assertEquals(6000.0, $kpis['cost_sharing']['gov_share_50']); // 50% = 6000
        $this->assertEquals(6000.0, $kpis['cost_sharing']['farmer_share_50']); // 50% = 6000
    }

    public function test_pii_masking_in_view_and_exports(): void
    {
        $app = $this->createWheatApplication($this->districtGilgit, '7150112345671', '03001234567', 10, 'pending');

        // Normal view: masked
        $response = $this->actingAs($this->directorAgri)->get(route('admin.wheat-mis.index'));
        $response->assertStatus(200)
            ->assertSee('71501-*****67-1')
            ->assertSee('0300-***4567');

        // Excel export: masked by default
        $exportResponse = $this->actingAs($this->directorAgri)->get(route('admin.wheat-mis.export', ['format' => 'csv']));
        $exportResponse->assertStatus(200);

        // Super Admin with unmask toggle
        $unmaskResponse = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.index', ['unmask' => 1]));
        $unmaskResponse->assertStatus(200)
            ->assertSee('71501-1234567-1')
            ->assertSee('03001234567');
    }

    public function test_analytics_json_endpoint(): void
    {
        $this->createWheatApplication($this->districtGilgit, '7150144444444', '03004444444', 12, 'pending');

        $response = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.analytics'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'kpis' => ['total_applications', 'land', 'bags', 'weights', 'cost_sharing'],
                'by_status' => ['labels', 'series'],
                'demand_supply' => ['labels', 'quotas', 'approved', 'requested', 'disbursed'],
                'land_bands' => ['labels', 'counts'],
                'water' => ['labels', 'series'],
                'road' => ['labels', 'series'],
            ]);
    }

    public function test_authorized_executive_can_update_rate(): void
    {
        $response = $this->actingAs($this->directorAgri)->post(route('admin.wheat-mis.rate'), [
            'password' => 'password',
            'wheat_seed_bag_rate' => 6500,
        ]);

        $response->assertRedirect();
        $this->assertEquals('6500', SiteSetting::getValue('wheat_seed_bag_rate'));
    }

    public function test_updating_seed_data_fails_without_correct_password(): void
    {
        $response = $this->actingAs($this->directorAgri)->post(route('admin.wheat-mis.rate'), [
            'password' => 'wrong-password',
            'wheat_seed_bag_rate' => 9999,
        ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals('9999', SiteSetting::getValue('wheat_seed_bag_rate'));
    }

    public function test_district_officer_cannot_update_rate(): void
    {
        $response = $this->actingAs($this->districtOfficer)->post(route('admin.wheat-mis.rate'), [
            'password' => 'password',
            'wheat_seed_bag_rate' => 6500,
        ]);

        $response->assertStatus(403);
    }

    public function test_authorized_executive_can_configure_comprehensive_seed_data(): void
    {
        $response = $this->actingAs($this->directorAgri)->post(route('admin.wheat-mis.rate'), [
            'password' => 'password',
            'wheat_seed_bag_rate' => 7200,
            'wheat_seed_bag_weight_kg' => 50,
            'wheat_seed_govt_share_pct' => 60,
            'wheat_seed_farmer_share_pct' => 40,
            'wheat_seed_kanals_per_bag' => 4.5,
            'wheat_seed_variety_name' => 'Certified Faisalabad-08',
            'wheat_seed_fiscal_year' => 'Rabi 2025-2026',
        ]);

        $response->assertRedirect();
        $this->assertEquals('7200', SiteSetting::getValue('wheat_seed_bag_rate'));
        $this->assertEquals('50', SiteSetting::getValue('wheat_seed_bag_weight_kg'));
        $this->assertEquals('60', SiteSetting::getValue('wheat_seed_govt_share_pct'));
        $this->assertEquals('40', SiteSetting::getValue('wheat_seed_farmer_share_pct'));
        $this->assertEquals('4.5', SiteSetting::getValue('wheat_seed_kanals_per_bag'));
        $this->assertEquals('Certified Faisalabad-08', SiteSetting::getValue('wheat_seed_variety_name'));

        // View displays configured seed data badges and button
        $viewResponse = $this->actingAs($this->directorAgri)->get(route('admin.wheat-mis.index'));
        $viewResponse->assertStatus(200)
            ->assertSee('Configure Seed Data')
            ->assertSee('PKR 7,200.00 / 50kg bag')
            ->assertSee('Cost Share: 60% Gov / 40% Farmer')
            ->assertSee('Sowing Baseline: 4.5 Kanals/Bag')
            ->assertSee('Certified Faisalabad-08');
    }

    public function test_pdf_export_renders(): void
    {
        $this->createWheatApplication($this->districtGilgit, '7150155555555', '03005555555', 8, 'directorate_approved');

        $response = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.export', ['format' => 'pdf']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_district_specific_transport_charges_and_delivered_rates(): void
    {
        // 1. Authorized executive configures base rate and district-specific transport surcharges
        $response = $this->actingAs($this->directorAgri)->post(route('admin.wheat-mis.rate'), [
            'password' => 'password',
            'wheat_seed_bag_rate' => 6500,
            'wheat_seed_bag_weight_kg' => 50,
            'wheat_seed_govt_share_pct' => 50,
            'wheat_seed_farmer_share_pct' => 50,
            'wheat_seed_kanals_per_bag' => 4,
            'district_transport' => [
                $this->districtGilgit->id => 100, // Gilgit carriage: PKR 100/bag -> 6,600 delivered
                $this->districtSkardu->id => 500, // Skardu carriage: PKR 500/bag -> 7,000 delivered
            ],
            'district_rate' => [
                $this->districtGilgit->id => 6600,
                $this->districtSkardu->id => 7000,
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('site_settings', ['key' => 'wheat_seed_bag_rate', 'value' => '6500']);
        $this->assertDatabaseHas('site_settings', ['key' => 'wheat_seed_district_rates']);

        // 2. Create approved applications in Gilgit (8 kanals = 2 bags) and Skardu (8 kanals = 2 bags)
        $this->createWheatApplication($this->districtGilgit, '7150111111111', '03001111111', 8, 'directorate_approved');
        $this->createWheatApplication($this->districtSkardu, '7150122222222', '03002222222', 8, 'directorate_approved');

        $service = app(WheatSeedMisService::class);

        // Verify service rates per district
        $this->assertEquals(6600, $service->getRateForDistrict($this->districtGilgit->id));
        $this->assertEquals(100, $service->getTransportCostForDistrict($this->districtGilgit->id));
        $this->assertEquals(7000, $service->getRateForDistrict($this->districtSkardu->id));
        $this->assertEquals(500, $service->getTransportCostForDistrict($this->districtSkardu->id));

        // Verify KPI calculations incorporating district-specific delivered rates
        $kpis = $service->getKpis();
        $this->assertTrue($kpis['cost_sharing']['is_configured']);
        $this->assertTrue($kpis['cost_sharing']['is_district_varying']);
        $this->assertEquals(6600, $kpis['cost_sharing']['rate_min']);
        $this->assertEquals(7000, $kpis['cost_sharing']['rate_max']);

        // Total Approved: 2 bags * 6600 (Gilgit) + 2 bags * 7000 (Skardu) = 13,200 + 14,000 = 27,200
        $this->assertEquals(27200, $kpis['cost_sharing']['total_approved_value']);
        $this->assertEquals(13600, $kpis['cost_sharing']['gov_share_50']);
        $this->assertEquals(13600, $kpis['cost_sharing']['farmer_share_50']);
        // Total Transport: (2 * 100) + (2 * 500) = 1200
        $this->assertEquals(1200, $kpis['cost_sharing']['total_transport_cost']);

        // Verify Demand-Supply Gap matrix carries district rate & valuation
        $gap = $service->getDemandSupplyGap();
        $gilgitGap = $gap->firstWhere('district_id', $this->districtGilgit->id);
        $skarduGap = $gap->firstWhere('district_id', $this->districtSkardu->id);

        $this->assertEquals(6600, $gilgitGap['rate_per_bag']);
        $this->assertEquals(100, $gilgitGap['transport_cost']);
        $this->assertEquals(13200, $gilgitGap['approved_cost']);
        $this->assertEquals(6600, $gilgitGap['gov_subsidy']);

        $this->assertEquals(7000, $skarduGap['rate_per_bag']);
        $this->assertEquals(500, $skarduGap['transport_cost']);
        $this->assertEquals(14000, $skarduGap['approved_cost']);
        $this->assertEquals(7000, $skarduGap['gov_subsidy']);

        // Verify dashboard renders the district varying badges and table rows
        $viewResponse = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.index'));
        $viewResponse->assertStatus(200)
            ->assertSee('Transport Adjusted')
            ->assertSee('PKR 6,600')
            ->assertSee('PKR 7,000')
            ->assertSee('PKR 27,200');
    }

    public function test_beneficiaries_ajax_endpoint_returns_rendered_html_and_pagination(): void
    {
        $this->createWheatApplication($this->districtGilgit, '7150199999991', '03009999991', 8, 'pending');
        $this->createWheatApplication($this->districtSkardu, '7150299999992', '03009999992', 16, 'district_verified');

        $response = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', ['per_page' => 25]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'html',
                'total',
                'current_page',
                'last_page',
                'per_page',
                'count',
            ]);

        $json = $response->json();
        $this->assertEquals(2, $json['total']);
        $this->assertStringContainsString('Wheat Beneficiary 9991', $json['html']);
        $this->assertStringContainsString('Wheat Beneficiary 9992', $json['html']);
    }

    public function test_beneficiaries_ajax_filters_by_district_tehsil_land_and_bags(): void
    {
        $app1 = $this->createWheatApplication($this->districtGilgit, '7150111111111', '03001111111', 4, 'pending'); // 1 bag
        $app2 = $this->createWheatApplication($this->districtGilgit, '7150122222222', '03002222222', 20, 'district_verified'); // 5 bags
        $app3 = $this->createWheatApplication($this->districtSkardu, '7150233333333', '03003333333', 40, 'directorate_approved'); // 10 bags

        // Filter by District
        $resDist = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', [
            'district_id' => $this->districtSkardu->id,
        ]));
        $this->assertEquals(1, $resDist->json('total'));
        $this->assertStringContainsString('Wheat Beneficiary 3333', $resDist->json('html'));
        $this->assertStringNotContainsString('Wheat Beneficiary 1111', $resDist->json('html'));

        // Filter by Min Land (Kanals >= 15)
        $resLand = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', [
            'min_land' => 15,
        ]));
        $this->assertEquals(2, $resLand->json('total'));
        $this->assertStringContainsString('Wheat Beneficiary 2222', $resLand->json('html'));
        $this->assertStringContainsString('Wheat Beneficiary 3333', $resLand->json('html'));
        $this->assertStringNotContainsString('Wheat Beneficiary 1111', $resLand->json('html'));

        // Filter by Min Bags (Min Bags >= 6)
        $resBags = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', [
            'min_bags' => 6,
        ]));
        $this->assertEquals(1, $resBags->json('total'));
        $this->assertStringContainsString('Wheat Beneficiary 3333', $resBags->json('html'));

        // Filter by Tehsil
        $resTehsil = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', [
            'tehsil' => 'Gilgit Tehsil',
        ]));
        $this->assertEquals(2, $resTehsil->json('total'));
    }

    public function test_beneficiaries_ajax_supports_per_page_all(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $cnic = sprintf('71501%08d', $i);
            $phone = sprintf('0300%07d', $i);
            $this->createWheatApplication($this->districtGilgit, $cnic, $phone, 8, 'pending');
        }

        // 25 per page has 2 pages
        $res25 = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', ['per_page' => 25]));
        $this->assertEquals(25, $res25->json('count'));
        $this->assertEquals(2, $res25->json('last_page'));

        // "all" shows all 30 records in 1 page
        $resAll = $this->actingAs($this->superAdmin)->getJson(route('admin.wheat-mis.beneficiaries', ['per_page' => 'all']));
        $this->assertEquals(30, $resAll->json('count'));
        $this->assertEquals(1, $resAll->json('last_page'));
        $this->assertStringContainsString('Showing all', $resAll->json('html'));
    }

    public function test_wheat_mis_export_downloads_excel_csv_and_pdf(): void
    {
        $this->createWheatApplication($this->districtGilgit, '7150188888881', '03008888881', 12, 'pending');

        // CSV export
        $csvRes = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.export', ['format' => 'csv']));
        $csvRes->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvRes->headers->get('content-type'));

        // Excel export
        $xlsxRes = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.export', ['format' => 'xlsx']));
        $xlsxRes->assertStatus(200);

        // PDF export
        $pdfRes = $this->actingAs($this->superAdmin)->get(route('admin.wheat-mis.export', ['format' => 'pdf']));
        $pdfRes->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $pdfRes->headers->get('content-type'));
    }

    private function createWheatApplication(District $district, string $cnic, string $phone, float $kanals, string $status): Application
    {
        $applicant = Applicant::create([
            'cnic' => $cnic,
            'full_name' => 'Wheat Beneficiary ' . substr($cnic, -4),
            'father_name' => 'Father Name',
            'phone' => $phone,
            'phone_verified' => true,
            'district_id' => $district->id,
            'tehsil' => $district->name . ' Tehsil',
            'union_council' => 'Central UC',
            'village' => 'Green Village',
            'land_location' => 'Main fertile parcel near stream',
        ]);

        $app = Application::create([
            'application_no' => Application::generateApplicationNo(),
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $district->id,
            'form_id' => 1,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $this->fieldLand->id,
            'value' => (string) $kanals,
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $this->fieldRoad->id,
            'value' => 'Yes',
        ]);

        ApplicationFieldValue::create([
            'application_id' => $app->id,
            'form_field_id' => $this->fieldWater->id,
            'value' => 'Yes',
        ]);

        return $app;
    }
}

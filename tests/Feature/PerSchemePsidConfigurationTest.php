<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\District;
use App\Models\GbpayApiCredential;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\User;
use App\Services\GbpayService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerSchemePsidConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Department $agriDept;
    private Department $livestockDept;
    private Department $fisheriesDept;
    private District $gilgit;
    private Scheme $schemeA;
    private Scheme $schemeB;
    private Scheme $schemeLivestock;
    private Scheme $schemeDefault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();
        $this->agriDept = Department::firstOrCreate(['slug' => 'agriculture'], ['name' => 'Agriculture', 'display_order' => 1]);
        $this->livestockDept = Department::firstOrCreate(['slug' => 'livestock'], ['name' => 'Livestock & Dairy Development', 'display_order' => 2]);
        $this->fisheriesDept = Department::firstOrCreate(['slug' => 'fisheries'], ['name' => 'Fisheries', 'display_order' => 3]);
        $this->gilgit = District::firstOrCreate(['code' => 'GLT'], ['name' => 'Gilgit', 'is_active' => true]);

        // Ensure known Global Default PSID Config
        PsidConfig::updateOrCreate(
            ['scheme_id' => null],
            [
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'district_code' => '01',
                'department_service_no' => '01',
                'rco' => 'GL-1618',
                'head' => 'C03870',
                'is_active' => true,
                'last_voucher_serial' => 0,
            ]
        );

        $this->schemeA = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Orchard Drip Irrigation Scheme',
            'slug' => 'orchard-drip-scheme-psid',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 5000,
        ]);

        $this->schemeB = Scheme::create([
            'department_id' => $this->fisheriesDept->id,
            'title' => 'Trout Fish Hatchery Support',
            'slug' => 'trout-hatchery-scheme-psid',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 8000,
        ]);

        $this->schemeLivestock = Scheme::create([
            'department_id' => $this->livestockDept->id,
            'title' => 'Dairy Farm Support Scheme',
            'slug' => 'dairy-farm-support-psid',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 4000,
        ]);

        $this->schemeDefault = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Standard Wheat Seed Scheme',
            'slug' => 'standard-wheat-scheme-psid',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 3000,
        ]);
    }

    private function makeAppForScheme(Scheme $scheme, int $idx): Application
    {
        $applicant = Applicant::create([
            'cnic' => '71501998877' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
            'full_name' => "Applicant {$idx}",
            'phone' => '030099887' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
            'phone_verified' => true,
            'district_id' => $this->gilgit->id,
        ]);

        return Application::create([
            'application_no' => 'ALF-PSID-' . str_pad((string) $idx, 4, '0', STR_PAD_LEFT),
            'scheme_id' => $scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'status' => 'pending',
            'current_stage' => Application::STAGE_APPROVED,
            'demanded_quantity' => 2,
            'approved_quantity' => 2,
            'submitted_at' => now(),
        ]);
    }

    public function test_admin_can_configure_distinct_psids_per_scheme_via_gbpay_and_bills_inherit_scheme_rules(): void
    {
        // Configure Scheme A via GBPay Console
        $resA = $this->actingAs($this->superAdmin)->put(route('admin.gbpay.psid-config.update'), [
            'scheme_id' => $this->schemeA->id,
            'one_link_prefix' => '202010',
            'department_code' => '12',
            'department_service_no' => '05',
            'rco' => 'GL-9901',
            'head' => 'C09911',
        ]);
        $resA->assertSessionHasNoErrors();

        // Configure Scheme B via GBPay Console
        $resB = $this->actingAs($this->superAdmin)->put(route('admin.gbpay.psid-config.update'), [
            'scheme_id' => $this->schemeB->id,
            'one_link_prefix' => '303020',
            'department_code' => '15',
            'department_service_no' => '08',
            'rco' => 'GL-8802',
            'head' => 'C07722',
        ]);
        $resB->assertSessionHasNoErrors();

        $gbpay = app(GbpayService::class);

        $billA = $gbpay->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeA, 1), 10000);
        $billB = $gbpay->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeB, 2), 16000);
        $billDefault = $gbpay->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeDefault, 3), 6000);
        $billLivestock = $gbpay->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeLivestock, 4), 8000);

        // Agriculture scheme keeps fixed 1Link (101530) + Dept (09) + fixed Agri Service (01) + serial #1
        $this->assertSame('10153009010000000001', $billA->psid);
        $this->assertSame(1, $billA->voucher_serial);
        $this->assertSame('C09911', $billA->challan_data['head']);

        // Fisheries scheme keeps fixed 1Link (101530) + Dept (09) + fixed Fisheries Service (03) + serial #2
        $this->assertSame('10153009030000000002', $billB->psid);
        $this->assertSame(2, $billB->voucher_serial);
        $this->assertSame('C07722', $billB->challan_data['head']);

        // Agriculture Default falls back to 101530 09 01 + serial #3
        $this->assertSame('10153009010000000003', $billDefault->psid);
        $this->assertSame(3, $billDefault->voucher_serial);
        $this->assertSame('C03870', $billDefault->challan_data['head']);

        // Livestock scheme (without custom override) automatically resolves Service Code 02 (101530 09 02) + serial #4
        $this->assertSame('10153009020000000004', $billLivestock->psid);
        $this->assertSame(4, $billLivestock->voucher_serial);

        // Global default remains intact
        $global = PsidConfig::getActive();
        $this->assertSame('1015300901', $global->formattedPrefix());
    }

    public function test_admin_can_configure_and_reset_scheme_psid_directly_in_scheme_edit_form(): void
    {
        // Enable custom PSID directly on Scheme Edit for Agriculture -> forced 101530 / 09 / 01
        $res = $this->actingAs($this->superAdmin)->put(route('admin.schemes.update', $this->schemeA), [
            'department_id' => $this->agriDept->id,
            'title' => $this->schemeA->title,
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => '1',
            'psid_settings_present' => '1',
            'use_custom_psid' => '1',
            'psid_one_link_prefix' => '555666',
            'psid_department_code' => '04',
            'psid_department_service_no' => '09',
            'psid_rco' => 'SD-1811',
            'psid_head' => 'C04455',
        ]);
        $res->assertRedirect(route('admin.schemes.index'));

        $effective = PsidConfig::forScheme($this->schemeA);
        $this->assertSame($this->schemeA->id, $effective->scheme_id);
        $this->assertSame('1015300901', $effective->formattedPrefix());
        $this->assertSame('C04455', $effective->head);

        $bill = app(GbpayService::class)->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeA, 10), 4000);
        $this->assertSame('10153009010000000001', $bill->psid);

        // Creating/updating Livestock scheme custom PSID forces Service Code 02
        $resLivestock = $this->actingAs($this->superAdmin)->put(route('admin.schemes.update', $this->schemeLivestock), [
            'department_id' => $this->livestockDept->id,
            'title' => $this->schemeLivestock->title,
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => '1',
            'psid_settings_present' => '1',
            'use_custom_psid' => '1',
            'psid_one_link_prefix' => '999999',
            'psid_department_code' => '88',
            'psid_department_service_no' => '77',
            'psid_rco' => 'GL-1618',
            'psid_head' => 'C03223',
        ]);
        $resLivestock->assertRedirect(route('admin.schemes.index'));
        $this->assertSame('1015300902', PsidConfig::forScheme($this->schemeLivestock)->formattedPrefix());

        // Verify GBPay console shows fixed readonly inputs and treasury reference guidance table
        $indexRes = $this->actingAs($this->superAdmin)->get(route('admin.gbpay.index'));
        $indexRes->assertOk();
        $indexRes->assertSee('Reference Guide: Treasury Account Heads', false);
        $indexRes->assertSee('C03116-AGRI RECIPTS (SALE OF FRUIT PLNTS, WHT)');
        $indexRes->assertSee('909552999910559');
        $indexRes->assertSee('909552999910659');
        $indexRes->assertSee('IN101530');
        $indexRes->assertSee('id="scheme_psid_prefix" maxlength="6" class="form-control form-control-sm bg-light font-monospace" value="101530" readonly', false);
        $indexRes->assertSee('id="scheme_psid_dept" maxlength="2" class="form-control form-control-sm bg-light font-monospace" value="09" readonly', false);
        $indexRes->assertSee('id="scheme_psid_svc" maxlength="2" class="form-control form-control-sm bg-light font-monospace" value="01" readonly', false);

        // Unchecking use_custom_psid reverts to global default
        $resReset = $this->actingAs($this->superAdmin)->put(route('admin.schemes.update', $this->schemeA), [
            'department_id' => $this->agriDept->id,
            'title' => $this->schemeA->title,
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => '1',
            'psid_settings_present' => '1',
        ]);
        $resReset->assertRedirect(route('admin.schemes.index'));

        $this->assertNull(PsidConfig::where('scheme_id', $this->schemeA->id)->first());
        $this->assertSame('1015300901', PsidConfig::forScheme($this->schemeA)->formattedPrefix());
    }

    public function test_removing_scheme_psid_override_via_gbpay_reverts_scheme_to_global_default(): void
    {
        $config = PsidConfig::create([
            'scheme_id' => $this->schemeA->id,
            'one_link_prefix' => '888999',
            'department_code' => '07',
            'district_code' => '01',
            'department_service_no' => '03',
            'rco' => 'GL-1618',
            'head' => 'C01122',
            'is_active' => true,
        ]);

        $this->assertSame('8889990703', PsidConfig::getActive($this->schemeA->id)->formattedPrefix());

        $delRes = $this->actingAs($this->superAdmin)->delete(route('admin.gbpay.psid-config.destroy', $config->config_id));
        $delRes->assertRedirect();

        $this->assertNull(PsidConfig::where('scheme_id', $this->schemeA->id)->first());
        $this->assertSame('1015300901', PsidConfig::getActive($this->schemeA->id)->formattedPrefix());
    }

    public function test_gbpay_bill_inquiry_reports_scheme_specific_head_code(): void
    {
        PsidConfig::create([
            'scheme_id' => $this->schemeB->id,
            'one_link_prefix' => '404040',
            'department_code' => '14',
            'district_code' => '01',
            'department_service_no' => '06',
            'rco' => 'GL-1618',
            'head' => 'C08899',
            'is_active' => true,
        ]);

        $bill = app(GbpayService::class)->createOrUpdateBillForApplication($this->makeAppForScheme($this->schemeB, 15), 12000);

        GbpayApiCredential::create([
            'username' => 'gbpay_scheme_test',
            'password' => Hash::make('secret1234'),
            'is_active' => true,
        ]);

        $inquiry = $this->postJson('/api/gb-pay/bill-inquiry', [
            'username' => 'gbpay_scheme_test',
            'password' => 'secret1234',
            'voucher_number' => $bill->psid,
        ]);

        $inquiry->assertOk();
        $inquiry->assertJsonPath('response_code', '00');
        $inquiry->assertJsonPath('details.0.code', 'C08899');
    }
}

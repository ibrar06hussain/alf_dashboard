<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Bill;
use App\Models\Department;
use App\Models\District;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\User;
use App\Models\VoucherSerial;
use App\Services\ApplicationLifecycleService;
use App\Services\GbpayService;
use Database\Seeders\RbacHierarchySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialVoucherSerialPsidTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $ddGilgit;
    private District $gilgit;
    private Department $agriDept;
    private Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacHierarchySeeder::class);

        $this->superAdmin = User::where('email', 'admin@alf.gb.gov.pk')->firstOrFail();
        $this->agriDept = Department::firstOrCreate(['slug' => 'agriculture'], ['name' => 'Agriculture', 'display_order' => 1]);
        $this->gilgit = District::firstOrCreate(['code' => 'GLT'], ['name' => 'Gilgit', 'is_active' => true]);

        $this->ddGilgit = User::factory()->create([
            'email' => 'dd.gilgit.voucher@alf.gb.gov.pk',
            'department_id' => $this->agriDept->id,
            'district_id' => $this->gilgit->id,
            'is_active' => true,
        ]);
        $this->ddGilgit->syncRoles(['District Deputy Director']);

        $this->scheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Wheat Seed Cost Sharing Test Scheme',
            'slug' => 'wheat-seed-voucher-serial-test',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 3000.00,
        ]);

        PsidConfig::updateOrCreate(
            ['is_active' => true],
            [
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'district_code' => '01',
                'department_service_no' => '01',
                'rco' => 'GL-1618',
                'head' => 'C03870',
                'last_voucher_serial' => 0,
            ]
        );
    }

    private function makeApplication(int $index): Application
    {
        $applicant = Applicant::create([
            'cnic' => '71501123456' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'full_name' => "Applicant {$index}",
            'phone' => '030012345' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'phone_verified' => true,
            'district_id' => $this->gilgit->id,
        ]);

        return Application::create([
            'application_no' => 'ALF-2026-VS-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'status' => 'pending',
            'current_stage' => Application::STAGE_UNDER_REVIEW,
            'demanded_quantity' => 5,
            'submitted_at' => now(),
        ]);
    }

    public function test_voucher_serial_starts_at_1_and_increments_sequentially_with_10_digit_zero_padding(): void
    {
        $gbpay = app(GbpayService::class);

        $app1 = $this->makeApplication(1);
        $app2 = $this->makeApplication(2);
        $app3 = $this->makeApplication(3);

        $bill1 = $gbpay->createOrUpdateBillForApplication($app1, 7500);
        $bill2 = $gbpay->createOrUpdateBillForApplication($app2, 7500);
        $bill3 = $gbpay->createOrUpdateBillForApplication($app3, 7500);

        $this->assertSame(1, $bill1->voucher_serial);
        $this->assertSame('10153009010000000001', $bill1->psid);
        $this->assertSame('0000000001', $bill1->formattedVoucherId());
        $this->assertSame(20, strlen($bill1->psid));

        $this->assertSame(2, $bill2->voucher_serial);
        $this->assertSame('10153009010000000002', $bill2->psid);
        $this->assertSame('0000000002', $bill2->formattedVoucherId());

        $this->assertSame(3, $bill3->voucher_serial);
        $this->assertSame('10153009010000000003', $bill3->psid);
        $this->assertSame('0000000003', $bill3->formattedVoucherId());

        $this->assertDatabaseHas('psid_config', ['last_voucher_serial' => 3]);
        $this->assertDatabaseHas('voucher_serials', [
            'serial_no' => 1,
            'voucher_code' => '0000000001',
            'bill_id' => $bill1->bill_id,
            'application_id' => $app1->id,
            'psid' => '10153009010000000001',
        ]);
        $this->assertDatabaseHas('voucher_serials', [
            'serial_no' => 3,
            'voucher_code' => '0000000003',
            'bill_id' => $bill3->bill_id,
            'application_id' => $app3->id,
            'psid' => '10153009010000000003',
        ]);
    }

    public function test_regenerating_or_redownloading_same_challan_does_not_duplicate_or_waste_serial(): void
    {
        $gbpay = app(GbpayService::class);
        $app1 = $this->makeApplication(10);

        $billFirst = $gbpay->createOrUpdateBillForApplication($app1, 5000);
        $this->assertSame(1, $billFirst->voucher_serial);
        $this->assertSame('10153009010000000001', $billFirst->psid);

        // Downloading challan PDF / syncing same pending bill reuses serial #1
        $billSynced = $gbpay->createOrUpdateBillForApplication($app1->fresh(), 5500, false);
        $this->assertSame($billFirst->bill_id, $billSynced->bill_id);
        $this->assertSame(1, $billSynced->voucher_serial);
        $this->assertSame('10153009010000000001', $billSynced->psid);
        $this->assertDatabaseHas('psid_config', ['last_voucher_serial' => 1]);
        $this->assertSame(1, VoucherSerial::count());

        // Forcing a brand new PSID increments cleanly to serial #2 without duplicating #1
        $billForced = $gbpay->createOrUpdateBillForApplication($app1->fresh(), 6000, true);
        $this->assertSame(2, $billForced->voucher_serial);
        $this->assertSame('10153009010000000002', $billForced->psid);
        $this->assertDatabaseHas('psid_config', ['last_voucher_serial' => 2]);
        $this->assertSame(2, VoucherSerial::count());
    }

    public function test_application_approval_and_challan_download_assign_sequential_voucher_serials(): void
    {
        $lifecycle = app(ApplicationLifecycleService::class);

        $app1 = $this->makeApplication(21);
        $app2 = $this->makeApplication(22);

        $lifecycle->transition($app1, Application::STAGE_APPROVED, $this->ddGilgit, [
            'approved_quantity' => 4,
            'challan_amount' => 6000,
        ]);

        $app1->refresh();
        $bill1 = Bill::where('application_id', $app1->id)->firstOrFail();
        $this->assertSame(1, $bill1->voucher_serial);
        $this->assertSame('10153009010000000001', $app1->psid);

        // Citizen downloads challan PDF -> should keep exact same serial #1
        $pdfResponse = $this->get(route('applications.challan.download', $app1->application_no));
        $pdfResponse->assertOk();
        $this->assertSame(1, VoucherSerial::count());

        // Second application approved -> gets serial #2
        $lifecycle->transition($app2, Application::STAGE_APPROVED, $this->ddGilgit, [
            'approved_quantity' => 5,
            'challan_amount' => 7500,
        ]);

        $app2->refresh();
        $bill2 = Bill::where('application_id', $app2->id)->firstOrFail();
        $this->assertSame(2, $bill2->voucher_serial);
        $this->assertSame('10153009010000000002', $app2->psid);
        $this->assertNotEquals($bill1->voucher_serial, $bill2->voucher_serial);
    }

    public function test_direct_psid_config_generate_psid_uses_sequential_database_serial(): void
    {
        $config = PsidConfig::getActive();

        $psid1 = $config->generatePsid();
        $psid2 = $config->generatePsid();

        $this->assertSame('10153009010000000001', $psid1);
        $this->assertSame('10153009010000000002', $psid2);
        $this->assertDatabaseHas('psid_config', ['last_voucher_serial' => 2]);
    }
}

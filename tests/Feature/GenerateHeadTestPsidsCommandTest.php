<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Bill;
use App\Models\Department;
use App\Models\District;
use App\Models\GbpayApiCredential;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\VoucherSerial;
use App\Services\GbpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GenerateHeadTestPsidsCommandTest extends TestCase
{
    use RefreshDatabase;

    private District $gilgit;
    private District $hunza;
    private Department $agriDept;
    private Department $livestockDept;
    private Department $fisheriesDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gilgit = District::create(['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);
        $this->hunza = District::create(['name' => 'Hunza', 'code' => 'HNZ', 'is_active' => true]);

        $this->agriDept = Department::create(['name' => 'Agriculture', 'slug' => 'agriculture', 'is_active' => true, 'display_order' => 1]);
        $this->livestockDept = Department::create(['name' => 'Livestock & Dairy Development', 'slug' => 'livestock', 'is_active' => true, 'display_order' => 2]);
        $this->fisheriesDept = Department::create(['name' => 'Fisheries', 'slug' => 'fisheries', 'is_active' => true, 'display_order' => 3]);

        // Seed 6 applicants to verify only the first 5 records (ordered by id) are used
        for ($i = 1; $i <= 6; $i++) {
            Applicant::create([
                'cnic' => '715010000000' . $i,
                'full_name' => "DB Applicant {$i}",
                'father_name' => "Father {$i}",
                'phone' => '0345000000' . $i,
                'phone_verified' => true,
                'district_id' => $i % 2 === 0 ? $this->hunza->id : $this->gilgit->id,
            ]);
        }

        // Update migration-seeded global PSID config with existing serials (simulating live DB before test run)
        PsidConfig::updateOrCreate(
            ['scheme_id' => null],
            [
                'one_link_prefix' => '101530',
                'department_code' => '09',
                'district_code' => '01',
                'department_service_no' => '01',
                'rco' => 'GL1618',
                'head' => 'C03116',
                'is_active' => true,
                'last_voucher_serial' => 10,
            ]
        );
    }

    public function test_command_generates_9_head_schemes_approves_first_5_applicants_and_reserves_psids(): void
    {
        $existingCsvs = glob(storage_path('app/gbpay_head_test_psids_*.csv')) ?: [];

        try {
            $this->artisan('gbpay:generate-head-test-psids')
                ->assertSuccessful();

            // 9 schemes created (all 10 heads except C03116)
            $this->assertSame(9, Scheme::count());
            $this->assertSame(0, PsidConfig::where('head', 'C03116')->whereNotNull('scheme_id')->count());
            $this->assertNull(Scheme::where('description', 'like', '%C03116%')->first());

        // 9 schemes * 5 first applicants = 45 approved applications & 45 real bills
        $this->assertSame(45, Application::count());
        $this->assertSame(45, Bill::count());
        $this->assertSame(45, VoucherSerial::count());

        // Applicant #6 was not included
        $sixthApplicant = Applicant::where('cnic', '7150100000006')->firstOrFail();
        $this->assertSame(0, Application::where('applicant_id', $sixthApplicant->id)->count());

        // Every generated PSID has valid 20 digits, correct department prefix, fee between 5..50, and unique voucher serial
        $bills = Bill::with(['application.scheme.department', 'application.district'])->get();
        $this->assertCount(45, $bills->pluck('psid')->unique());

        foreach ($bills as $bill) {
            $app = $bill->application;
            $expectedSvc = PsidConfig::serviceCodeForDepartment($app->scheme->department);
            $expectedRco = PsidConfig::resolveDistrictRco($app->district);

            $this->assertEquals('directorate_approved', $app->status);
            $this->assertEquals(Application::STAGE_CHALLAN_ISSUED, $app->current_stage);
            $this->assertStringStartsWith('10153009' . $expectedSvc, $bill->psid);
            $this->assertSame(20, strlen($bill->psid));
            $this->assertGreaterThanOrEqual(5, (int) $bill->bill_amount);
            $this->assertLessThanOrEqual(50, (int) $bill->bill_amount);
            $this->assertSame($expectedRco, $bill->challan_data['rco']);
        }

        // Verify Bill Inquiry returns the scheme-specific account head and district RCO
        GbpayApiCredential::create([
            'username' => 'gbpay_head_test',
            'password' => Hash::make('secret1234'),
            'is_active' => true,
        ]);

        $fisheriesBill = $bills->first(fn ($b) => str_starts_with($b->psid, '1015300903'));
        $this->assertNotNull($fisheriesBill);
        $inquiry = $this->postJson('/api/gb-pay/bill-inquiry', [
            'username' => 'gbpay_head_test',
            'password' => 'secret1234',
            'voucher_number' => $fisheriesBill->psid,
        ]);
        $inquiry->assertOk();
        $inquiry->assertJsonPath('response_code', '00');
        $inquiry->assertJsonPath('details.0.code', 'C03201');
        $inquiry->assertJsonPath('rco_code', PsidConfig::resolveDistrictRco($fisheriesBill->application->district));

        // Verify any future PSID issued to a brand-new citizen NEVER collides with any of the 45 test serials/PSIDs
        $futureScheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Future Production Scheme',
            'slug' => 'future-production-scheme',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
        ]);
        $futureApp = Application::create([
            'application_no' => 'ALF-FUTURE-1',
            'scheme_id' => $futureScheme->id,
            'applicant_id' => $sixthApplicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'status' => 'directorate_approved',
            'current_stage' => Application::STAGE_CHALLAN_ISSUED,
            'demanded_quantity' => 1,
            'approved_quantity' => 1,
            'submitted_at' => now(),
        ]);

        $futureBill = app(GbpayService::class)->createOrUpdateBillForApplication($futureApp, 1000);
        $this->assertGreaterThan(55, $futureBill->voucher_serial);
        $this->assertFalse($bills->pluck('psid')->contains($futureBill->psid));

        // Running the command a second time is idempotent (reuses existing 45 apps, creates 0 duplicates)
        $this->artisan('gbpay:generate-head-test-psids')
            ->assertSuccessful();
        $this->assertSame(46, Application::count()); // 45 test + 1 futureApp
        $this->assertSame(46, Bill::count());
        } finally {
            $afterCsvs = glob(storage_path('app/gbpay_head_test_psids_*.csv')) ?: [];
            foreach (array_diff($afterCsvs, $existingCsvs) as $csvPath) {
                @unlink($csvPath);
            }
        }
    }
}

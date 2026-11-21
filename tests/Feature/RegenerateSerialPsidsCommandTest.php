<?php

namespace Tests\Feature;

use App\Console\Commands\RegenerateSerialPsidsCommand;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Bill;
use App\Models\Department;
use App\Models\District;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\VoucherSerial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RegenerateSerialPsidsCommandTest extends TestCase
{
    use RefreshDatabase;

    private District $gilgit;
    private Department $agriDept;
    private Scheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gilgit = District::create(['name' => 'Gilgit', 'code' => 'GLT', 'is_active' => true]);
        $this->agriDept = Department::create(['name' => 'Agriculture', 'slug' => 'agriculture', 'is_active' => true, 'display_order' => 1]);
        $this->scheme = Scheme::create([
            'department_id' => $this->agriDept->id,
            'title' => 'Wheat Seed Cost Sharing',
            'slug' => 'distribution-of-wheat-seed-cost-sharing',
            'type' => 'running',
            'scope' => 'gb',
            'status' => 'active',
            'requires_challan' => true,
            'challan_fee_per_unit' => 3000,
        ]);

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

    public function test_regenerates_only_eligible_pending_non_serial_bills_and_preserves_paid_or_cancelled(): void
    {
        $existingCsvs = glob(storage_path('app/gbpay_regenerated_psids_*.csv')) ?: [];

        try {
            // 1. Eligible pending legacy non-serial bill with expired due date
            $appPending = $this->seedApplication('ALF-LEGACY-PENDING-1', '7150110000001', Application::STAGE_CHALLAN_ISSUED, '2026-11-01');
            $oldPendingBill = Bill::create([
                'application_id' => $appPending->id,
                'psid' => '10153015061910858604',
                'voucher_serial' => null,
                'bill_amount' => 8725,
                'status' => 'pending',
                'generated_at' => now()->subDays(20),
                'due_date' => '2026-11-01',
            ]);
            $appPending->forceFill(['psid' => $oldPendingBill->psid])->saveQuietly();

            // 2. Paid legacy non-serial bill (must NOT be recreated)
            $appPaid = $this->seedApplication('ALF-LEGACY-PAID-1', '7150110000002', Application::STAGE_PAYMENT_VERIFIED, '2026-11-01');
            $oldPaidBill = Bill::create([
                'application_id' => $appPaid->id,
                'psid' => '10153015061992613816',
                'voucher_serial' => null,
                'bill_amount' => 8725,
                'status' => 'paid',
                'generated_at' => now()->subDays(20),
                'paid_at' => now()->subDays(18),
            ]);
            $appPaid->forceFill(['psid' => $oldPaidBill->psid, 'challan_paid_at' => now()->subDays(18)])->saveQuietly();

            // 3. Cancelled legacy non-serial bill (must NOT be recreated)
            $appCancelled = $this->seedApplication('ALF-LEGACY-CANC-1', '7150110000003', Application::STAGE_DORMANT, '2026-11-01');
            $oldCancelledBill = Bill::create([
                'application_id' => $appCancelled->id,
                'psid' => '10153099991715097737',
                'voucher_serial' => null,
                'bill_amount' => 3000,
                'status' => 'cancelled',
                'generated_at' => now()->subDays(20),
            ]);

            // 4. Orphan pending non-serial bill on an app that ALSO has a paid bill (must NOT recreate)
            $orphanPendingOnPaid = Bill::create([
                'application_id' => $appPaid->id,
                'psid' => '10153009016817489749',
                'voucher_serial' => null,
                'bill_amount' => 2,
                'status' => 'pending',
                'generated_at' => now()->subDays(10),
            ]);

            // 5. Already-valid serial-format pending bill (must remain untouched)
            $serialRec = VoucherSerial::allocateNext();
            $validPsid = '1015300901' . VoucherSerial::formatVoucherId($serialRec->serial_no);
            $appValid = $this->seedApplication('ALF-SERIAL-OK-1', '7150110000004', Application::STAGE_CHALLAN_ISSUED, now()->addDays(10)->toDateString());
            $validBill = Bill::create([
                'application_id' => $appValid->id,
                'psid' => $validPsid,
                'voucher_serial' => $serialRec->serial_no,
                'bill_amount' => 3000,
                'status' => 'pending',
                'generated_at' => now(),
            ]);
            $serialRec->update(['bill_id' => $validBill->bill_id, 'application_id' => $appValid->id, 'psid' => $validPsid]);
            $appValid->forceFill(['psid' => $validPsid])->saveQuietly();

            // Dry run makes zero changes
            $this->artisan('gbpay:regenerate-serial-psids --dry-run')
                ->expectsOutputToContain('ALF-LEGACY-PENDING-1')
                ->assertSuccessful();
            $this->assertSame('pending', $oldPendingBill->fresh()->status);

            // Live run (--no-pdf here; PDF download route tested below)
            $exitCode = Artisan::call('gbpay:regenerate-serial-psids', ['--no-pdf' => true]);
            $output = Artisan::output();
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('ALF-LEGACY-PENDING-1', $output);
            $this->assertStringContainsString('10153015061910858604', $output);
            $this->assertStringContainsString('10153009010000000012', $output);

            // Old pending bill is cancelled; new serial bill is pending with serial #12
            $this->assertSame('cancelled', $oldPendingBill->fresh()->status);
            $newBill = Bill::where('application_id', $appPending->id)->where('status', 'pending')->first();
            $this->assertNotNull($newBill);
            $this->assertSame(12, $newBill->voucher_serial);
            $this->assertSame('10153009010000000012', $newBill->psid);
            $this->assertSame(8725, $newBill->bill_amount);
            $this->assertTrue(RegenerateSerialPsidsCommand::isSerialFormatBill($newBill->fresh(['application.scheme'])));

            // Application PSID & extended challan due date updated so citizen can download the new challan
            $appPending->refresh();
            $this->assertSame('10153009010000000012', $appPending->psid);
            $this->assertGreaterThan(now()->toDateString(), $appPending->challan_due_date->format('Y-m-d'));

            $pdfResp = $this->get(route('applications.challan.download', $appPending->application_no));
            $pdfResp->assertOk();
            $this->assertStringContainsString('application/pdf', (string) $pdfResp->headers->get('content-type'));

            // Paid, cancelled, orphan-on-paid, and already-valid serial bills are untouched
            $this->assertSame('paid', $oldPaidBill->fresh()->status);
            $this->assertSame('10153015061992613816', $oldPaidBill->fresh()->psid);
            $this->assertSame('cancelled', $oldCancelledBill->fresh()->status);
            $this->assertSame('pending', $orphanPendingOnPaid->fresh()->status);
            $this->assertSame($validPsid, $validBill->fresh()->psid);
            $this->assertSame(1, Bill::where('application_id', $appValid->id)->count());

            // Second run finds 0 pending candidates to regenerate
            $this->artisan('gbpay:regenerate-serial-psids --no-pdf')
                ->expectsOutputToContain('No eligible pending non-serial bills found to regenerate.')
                ->assertSuccessful();
        } finally {
            $afterCsvs = glob(storage_path('app/gbpay_regenerated_psids_*.csv')) ?: [];
            foreach (array_diff($afterCsvs, $existingCsvs) as $csvPath) {
                @unlink($csvPath);
            }
        }
    }

    private function seedApplication(string $appNo, string $cnic, string $stage, string $dueDate): Application
    {
        $applicant = Applicant::create([
            'cnic' => $cnic,
            'full_name' => "Applicant {$appNo}",
            'father_name' => 'Father Name',
            'phone' => '0345' . substr($cnic, -7),
            'district_id' => $this->gilgit->id,
        ]);

        return Application::create([
            'application_no' => $appNo,
            'scheme_id' => $this->scheme->id,
            'applicant_id' => $applicant->id,
            'district_id' => $this->gilgit->id,
            'department_id' => $this->agriDept->id,
            'status' => $stage === Application::STAGE_CHALLAN_ISSUED ? 'directorate_approved' : 'pending',
            'current_stage' => $stage,
            'demanded_quantity' => 1,
            'approved_quantity' => 1,
            'challan_no' => 'CHL-' . $appNo,
            'challan_issued_at' => now()->subDays(20),
            'challan_due_date' => $dueDate,
            'submitted_at' => now()->subDays(20),
        ]);
    }
}

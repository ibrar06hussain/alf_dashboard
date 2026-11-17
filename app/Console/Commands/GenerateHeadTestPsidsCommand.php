<?php

namespace App\Console\Commands;

use App\Models\AccessAuditLog;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Bill;
use App\Models\ChallanTemplate;
use App\Models\Department;
use App\Models\District;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\VoucherSerial;
use App\Services\GbpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateHeadTestPsidsCommand extends Command
{
    protected $signature = 'gbpay:generate-head-test-psids
                            {--min-fee=5 : Minimum random fee in PKR}
                            {--max-fee=50 : Maximum random fee in PKR}
                            {--applicants=5 : Number of applicant records per scheme to use}
                            {--offset=0 : Skip the first N applicant records before selecting}
                            {--next : Select the next applicants who do not yet have an application on each test scheme}
                            {--validity-days=30 : Challan & PSID validity window in days}
                            {--dry-run : Preview schemes, applicants, and service codes without writing to database}';

    protected $description = 'Create 1 test scheme per Treasury account head (excluding C03116), approve applications for the first 5 applicants, and generate permanently reserved real 20-digit PSIDs';

    /**
     * Account heads to test (excludes C03116 which is already live in production).
     */
    private const HEAD_DEFINITIONS = [
        [
            'head' => 'C03117',
            'dept_key' => 'agriculture',
            'service_code' => '01',
            'slug' => 'gbpay-test-c03117-exp-farms-plants',
            'title' => 'GBPay Test - C03117 (Experimental Farms Produce)',
            'description' => 'C03117 - RCPTS FRM EXP FRMS (SALE OF PLATS PC)',
        ],
        [
            'head' => 'C03118',
            'dept_key' => 'agriculture',
            'service_code' => '01',
            'slug' => 'gbpay-test-c03118-agr-farms-cer-seed',
            'title' => 'GBPay Test - C03118 (Certified Seed Sale)',
            'description' => 'C03118 - REC FRM AGR FRMS (SALE OF CER SEED)',
        ],
        [
            'head' => 'C03136',
            'dept_key' => 'agriculture',
            'service_code' => '01',
            'slug' => 'gbpay-test-c03136-agri-auction-stores',
            'title' => 'GBPay Test - C03136 (Auction of Stores)',
            'description' => 'C03136 - AGRICULTURE OTHERS (AUCTION OF STORES)',
        ],
        [
            'head' => 'C03138',
            'dept_key' => 'agriculture',
            'service_code' => '01',
            'slug' => 'gbpay-test-c03138-seed-registration-fee',
            'title' => 'GBPay Test - C03138 (Seed Registration Fee)',
            'description' => 'C03138 - REGISTRATION FEE SEED (REGISTRATN)',
        ],
        [
            'head' => 'C03130',
            'dept_key' => 'agriculture',
            'service_code' => '01',
            'slug' => 'gbpay-test-c03130-agri-govt-gardens',
            'title' => 'GBPay Test - C03130 (Government Gardens Receipts)',
            'description' => 'C03130 - AGRI REC FRM GVRNMNT GRDNS',
        ],
        [
            'head' => 'C03223',
            'dept_key' => 'livestock',
            'service_code' => '02',
            'slug' => 'gbpay-test-c03223-livestock-farms-receipts',
            'title' => 'GBPay Test - C03223 (Livestock Farms Receipts)',
            'description' => 'C03223 - REC FRM LVSTCK FRMS',
        ],
        [
            'head' => 'C03227',
            'dept_key' => 'livestock',
            'service_code' => '02',
            'slug' => 'gbpay-test-c03227-ai-fee-adc',
            'title' => 'GBPay Test - C03227 (Artificial Insemination Fee)',
            'description' => 'C03227 - ARTIFICIAL INSEMINATION FEE-ADC GOGB',
        ],
        [
            'head' => 'C03231',
            'dept_key' => 'livestock',
            'service_code' => '02',
            'slug' => 'gbpay-test-c03231-livestock-animals-birds',
            'title' => 'GBPay Test - C03231 (Share of Animals and Birds)',
            'description' => 'C03231 - OTHRS (SHARE OF ANIMALS AND BIRDS)',
        ],
        [
            'head' => 'C03201',
            'dept_key' => 'fisheries',
            'service_code' => '03',
            'slug' => 'gbpay-test-c03201-fish-and-seed-sale',
            'title' => 'GBPay Test - C03201 (Table Fish & Fish Seed Sale)',
            'description' => 'C03201 - SALE OF TABLE FISH, FISH SEED SALE',
        ],
    ];

    public function handle(GbpayService $gbpayService): int
    {
        $minFee = max(1, (int) $this->option('min-fee'));
        $maxFee = max($minFee, (int) $this->option('max-fee'));
        $limit = max(1, (int) $this->option('applicants'));
        $offset = max(0, (int) $this->option('offset'));
        $nextOnly = (bool) $this->option('next');
        $validityDays = max(1, (int) $this->option('validity-days'));
        $dryRun = (bool) $this->option('dry-run');

        $totalApplicants = Applicant::count();
        if ($totalApplicants === 0) {
            $this->error('No applicant records exist in the database.');
            return Command::FAILURE;
        }

        $fallbackDistrict = District::orderBy('id')->first();
        $firstApplicantWithDistrict = Applicant::whereNotNull('district_id')->orderBy('id')->first();
        if (! $fallbackDistrict && ! $firstApplicantWithDistrict) {
            $this->error('No districts exist in the database to resolve district RCO.');
            return Command::FAILURE;
        }

        $departments = $this->resolveDepartments($dryRun);
        if (in_array(null, $departments, true)) {
            $this->error('Could not resolve all required departments (Agriculture, Livestock, Fisheries).');
            return Command::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Generating schemes, approvals, and real reserved PSIDs for ' . count(self::HEAD_DEFINITIONS) . " account heads × up to {$limit} applicants...");

        if ($dryRun) {
            $previewRows = [];
            foreach (self::HEAD_DEFINITIONS as $def) {
                $dept = $departments[$def['dept_key']];
                $existingScheme = Scheme::where('slug', $def['slug'])->first();
                $schemeApplicants = $this->selectApplicantsForScheme($existingScheme, $limit, $offset, $nextOnly);

                if ($schemeApplicants->count() < $limit) {
                    $this->warn("Head {$def['head']}: requested {$limit} applicants, found {$schemeApplicants->count()}.");
                }

                foreach ($schemeApplicants as $applicant) {
                    $dist = $applicant->district ?? $fallbackDistrict;
                    $previewRows[] = [
                        $def['head'],
                        $dept->name,
                        PsidConfig::serviceCodeForDepartment($dept),
                        "10153009" . PsidConfig::serviceCodeForDepartment($dept),
                        "#{$applicant->id} {$applicant->full_name}",
                        $dist?->name ?? '—',
                        PsidConfig::resolveDistrictRco($dist),
                        "PKR {$minFee}-{$maxFee}",
                    ];
                }
            }
            $this->table(['Head', 'Department', 'Svc', 'Prefix', 'Applicant', 'District', 'RCO', 'Fee Range'], $previewRows);
            return Command::SUCCESS;
        }

        $results = [];
        $createdSchemes = 0;
        $createdApps = 0;
        $reusedApps = 0;

        foreach (self::HEAD_DEFINITIONS as $def) {
            $dept = $departments[$def['dept_key']];
            $serviceCode = PsidConfig::serviceCodeForDepartment($dept);

            $template = ChallanTemplate::updateOrCreate(
                ['name' => "Live Test Challan - {$def['head']}"],
                [
                    'department_name' => $dept->name . ' Department, Gilgit-Baltistan',
                    'sub_title' => 'Government of Gilgit-Baltistan',
                    'challan_title' => 'FEE DEPOSIT CHALLAN',
                    'bank_name' => 'National Bank Of Pakistan',
                    'account_title' => $dept->name . ' Department Gilgit Baltistan',
                    'account_no' => '4256260305',
                    'account_head' => $def['description'],
                    'rco_code' => 'GL1618',
                    'head_code' => $def['head'],
                    'validity_days' => $validityDays,
                    'directions' => 'Generated for live GBPay / 1Link account head testing.',
                    'fee_mode' => 'fixed_total',
                    'govt_share_pct' => 0.00,
                    'farmer_share_pct' => 100.00,
                    'show_breakdown' => false,
                    'is_active' => true,
                    'is_default' => false,
                ]
            );

            $scheme = Scheme::where('slug', $def['slug'])->first();
            if (! $scheme) {
                $scheme = Scheme::create([
                    'department_id' => $dept->id,
                    'title' => $def['title'],
                    'slug' => $def['slug'],
                    'type' => 'running',
                    'scope' => 'gb',
                    'status' => 'active',
                    'description' => $def['description'],
                    'is_featured' => false,
                    'requires_challan' => true,
                    'challan_fee_per_unit' => $minFee,
                    'challan_template_id' => $template->id,
                ]);
                $createdSchemes++;
            } else {
                $scheme->update([
                    'department_id' => $dept->id,
                    'requires_challan' => true,
                    'challan_template_id' => $template->id,
                ]);
            }

            PsidConfig::updateOrCreate(
                ['scheme_id' => $scheme->id],
                [
                    'one_link_prefix' => '101530',
                    'department_code' => '09',
                    'district_code' => '01',
                    'department_service_no' => $serviceCode,
                    'rco' => 'GL1618',
                    'head' => $def['head'],
                    'is_active' => true,
                ]
            );

            $schemeApplicants = $this->selectApplicantsForScheme($scheme, $limit, $offset, $nextOnly);
            if ($schemeApplicants->count() < $limit) {
                $this->warn("Head {$def['head']}: requested {$limit} applicants, found {$schemeApplicants->count()}.");
            }

            foreach ($schemeApplicants as $applicant) {
                $dist = $applicant->district ?? $fallbackDistrict;
                $districtId = $dist->id;

                $existingApp = Application::where('scheme_id', $scheme->id)
                    ->where('applicant_id', $applicant->id)
                    ->first();

                if ($existingApp) {
                    $bill = $existingApp->bills()->orderByDesc('bill_id')->first()
                        ?? $gbpayService->createOrUpdateBillForApplication(
                            $existingApp,
                            (float) ($existingApp->challan_amount ?: random_int($minFee, $maxFee))
                        );

                    $reusedApps++;
                    $results[] = [
                        'head' => $def['head'],
                        'scheme' => $scheme->title,
                        'svc' => $serviceCode,
                        'applicant' => "#{$applicant->id} {$applicant->full_name}",
                        'cnic' => $applicant->cnic,
                        'district' => $dist->name,
                        'rco' => $bill->challan_data['rco'] ?? PsidConfig::resolveDistrictRco($dist),
                        'app_no' => $existingApp->application_no,
                        'challan_no' => $existingApp->challan_no,
                        'psid' => $bill->psid,
                        'serial' => $bill->voucher_serial,
                        'fee_pkr' => (int) ceil((float) $bill->bill_amount),
                        'status' => strtoupper((string) $bill->status) . ' (existing)',
                    ];
                    continue;
                }

                $fee = random_int($minFee, $maxFee);

                $record = DB::transaction(function () use ($scheme, $dept, $applicant, $dist, $districtId, $def, $fee, $validityDays, $gbpayService) {
                    $divisionId = Schema::hasTable('division_districts')
                        ? DB::table('division_districts')->where('district_id', $districtId)->value('division_id')
                        : null;

                    $appNo = sprintf('ALF-TEST-%s-%04d', $def['head'], $applicant->id);
                    while (Application::where('application_no', $appNo)->exists()) {
                        $appNo = Application::generateApplicationNo();
                    }

                    $application = Application::create([
                        'application_no' => $appNo,
                        'scheme_id' => $scheme->id,
                        'applicant_id' => $applicant->id,
                        'district_id' => $districtId,
                        'division_id' => $divisionId,
                        'department_id' => $dept->id,
                        'status' => 'directorate_approved',
                        'current_stage' => Application::STAGE_CHALLAN_ISSUED,
                        'demanded_quantity' => 1,
                        'approved_quantity' => 1,
                        'allocated_quantity' => 1,
                        'verification_note' => "Approved for live GBPay {$def['head']} account-head PSID test (SMS suppressed).",
                        'submitted_at' => now(),
                        'received_at' => now(),
                        'stage_updated_at' => now(),
                        'challan_amount' => $fee,
                        'challan_issued_at' => now(),
                        'challan_due_date' => now()->addDays($validityDays)->toDateString(),
                        'lock_version' => 1,
                    ]);

                    $application->challan_no = 'CHL-' . date('Y') . '-' . str_pad((string) $application->id, 6, '0', STR_PAD_LEFT);
                    $application->saveQuietly();

                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'from_status' => Application::STAGE_SUBMITTED,
                        'to_status' => Application::STAGE_CHALLAN_ISSUED,
                        'note' => "Approved for live account-head PSID test ({$def['head']}) with fee Rs. {$fee}.",
                    ]);

                    // Atomically reserves next sequential voucher_serial + permanently stores PSID in voucher_serials & bills
                    $bill = $gbpayService->createOrUpdateBillForApplication($application, (float) $fee);

                    AccessAuditLog::log('gbpay_head_test_psid_generated', Application::class, (string) $application->id, [
                        'head' => $def['head'],
                        'psid' => $bill->psid,
                        'voucher_serial' => $bill->voucher_serial,
                        'amount' => $fee,
                        'district' => $dist->name,
                    ]);

                    return [$application, $bill];
                });

                /** @var Application $createdApp */
                /** @var Bill $bill */
                [$createdApp, $bill] = $record;
                $createdApps++;

                $results[] = [
                    'head' => $def['head'],
                    'scheme' => $scheme->title,
                    'svc' => $serviceCode,
                    'applicant' => "#{$applicant->id} {$applicant->full_name}",
                    'cnic' => $applicant->cnic,
                    'district' => $dist->name,
                    'rco' => $bill->challan_data['rco'] ?? PsidConfig::resolveDistrictRco($dist),
                    'app_no' => $createdApp->application_no,
                    'challan_no' => $createdApp->challan_no,
                    'psid' => $bill->psid,
                    'serial' => $bill->voucher_serial,
                    'fee_pkr' => (int) ceil((float) $bill->bill_amount),
                    'status' => strtoupper((string) $bill->status),
                ];
            }
        }

        // Write CSV manifest for easy tracking during live bank / 1Link testing
        $csvFilename = 'gbpay_head_test_psids_' . now()->format('Ymd_His') . '.csv';
        $csvPath = storage_path('app/' . $csvFilename);
        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0755, true);
        }
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['Head', 'Scheme', 'Service', 'Applicant', 'CNIC', 'District', 'RCO', 'Application No', 'Challan No', 'PSID', 'Voucher Serial', 'Fee PKR', 'Status']);
        foreach ($results as $row) {
            fputcsv($fp, array_values($row));
        }
        fclose($fp);

        $this->table(
            ['Head', 'Svc', 'Applicant', 'District', 'RCO', 'App No', 'PSID (20d)', 'Serial', 'Fee (PKR)', 'Status'],
            array_map(fn ($r) => [
                $r['head'],
                $r['svc'],
                $r['applicant'],
                $r['district'],
                $r['rco'],
                $r['app_no'],
                $r['psid'],
                $r['serial'],
                $r['fee_pkr'],
                $r['status'],
            ], $results)
        );

        $maxSerial = VoucherSerial::max('serial_no');
        $this->info("Done! Schemes created: {$createdSchemes} | Applications created & approved: {$createdApps} | Reused existing: {$reusedApps}");
        $this->info("All PSIDs are permanently locked in voucher_serials (latest serial_no: {$maxSerial}). CSV saved to: {$csvPath}");

        return Command::SUCCESS;
    }

    /**
     * Resolve Agriculture, Livestock, and Fisheries department models.
     *
     * @return array<string, ?Department>
     */
    private function resolveDepartments(bool $dryRun): array
    {
        $agri = Department::where('slug', 'agriculture')
            ->orWhere('name', 'like', '%Agriculture%')
            ->first();
        $livestock = Department::where('slug', 'livestock')
            ->orWhere('name', 'like', '%Livestock%')
            ->first();
        $fisheries = Department::where('slug', 'fisheries')
            ->orWhere('name', 'like', '%Fisher%')
            ->first();

        if (! $dryRun) {
            $agri = $agri ?: Department::firstOrCreate(
                ['slug' => 'agriculture'],
                ['name' => 'Agriculture', 'is_active' => true, 'display_order' => 1]
            );
            $livestock = $livestock ?: Department::firstOrCreate(
                ['slug' => 'livestock'],
                ['name' => 'Livestock & Dairy Development', 'is_active' => true, 'display_order' => 2]
            );
            $fisheries = $fisheries ?: Department::firstOrCreate(
                ['slug' => 'fisheries'],
                ['name' => 'Fisheries', 'is_active' => true, 'display_order' => 3]
            );
        }

        return [
            'agriculture' => $agri,
            'livestock' => $livestock,
            'fisheries' => $fisheries,
        ];
    }

    /**
     * Select applicants for a test scheme, optionally skipping applicants already enrolled in that scheme.
     */
    private function selectApplicantsForScheme(?Scheme $scheme, int $limit, int $offset, bool $nextOnly)
    {
        $query = Applicant::with('district')->orderBy('id');

        if ($nextOnly && $scheme) {
            $query->whereNotIn('id', function ($sub) use ($scheme) {
                $sub->select('applicant_id')
                    ->from('applications')
                    ->where('scheme_id', $scheme->id);
            });
        }

        if ($offset > 0) {
            $query->skip($offset);
        }

        return $query->limit($limit)->get();
    }
}

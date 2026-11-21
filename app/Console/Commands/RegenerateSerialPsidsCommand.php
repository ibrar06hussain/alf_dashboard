<?php

namespace App\Console\Commands;

use App\Models\AccessAuditLog;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\Bill;
use App\Models\PsidConfig;
use App\Models\Scheme;
use App\Models\VoucherSerial;
use App\Services\GbpayService;
use App\Support\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateSerialPsidsCommand extends Command
{
    protected $signature = 'gbpay:regenerate-serial-psids
                            {--scheme= : Optionally limit to a specific scheme ID or slug}
                            {--validity-days= : Override challan validity days from today (defaults to template validity_days or 14)}
                            {--no-pdf : Skip saving rendered challan PDF copies to storage/app/regenerated_challans}
                            {--dry-run : Preview non-serial PSIDs and skip/regenerate plan without modifying database}';

    protected $description = 'Identify non-serial PSIDs, skip paid/cancelled bills, regenerate pending bills in sequential serial format with updated challans, and list old PSID, new PSID, and application no';

    public static function isSerialFormatBill(Bill $bill): bool
    {
        $psid = (string) $bill->psid;
        if (! preg_match('/^\d{20}$/', $psid)) {
            return false;
        }

        if ($bill->voucher_serial === null || (int) $bill->voucher_serial <= 0) {
            return false;
        }

        $scheme = $bill->application?->scheme;
        $expectedPrefix = $scheme
            ? PsidConfig::getActive($scheme->id)->formattedPrefix()
            : '1015300901';

        $expectedPsid = $expectedPrefix . VoucherSerial::formatVoucherId((int) $bill->voucher_serial);

        return $psid === $expectedPsid;
    }

    public function handle(GbpayService $gbpayService): int
    {
        $schemeFilter = trim((string) ($this->option('scheme') ?? ''));
        $dryRun = (bool) $this->option('dry-run');
        $skipPdf = (bool) $this->option('no-pdf');
        $overrideDays = $this->option('validity-days') !== null && $this->option('validity-days') !== ''
            ? max(1, (int) $this->option('validity-days'))
            : null;

        $schemeIdFilter = null;
        if ($schemeFilter !== '') {
            $schemeModel = is_numeric($schemeFilter)
                ? Scheme::find((int) $schemeFilter)
                : Scheme::where('slug', $schemeFilter)->first();

            if (! $schemeModel) {
                $this->error("Scheme '{$schemeFilter}' not found.");
                return Command::FAILURE;
            }
            $schemeIdFilter = $schemeModel->id;
        }

        $billsQuery = Bill::with([
            'application.scheme.department',
            'application.scheme.challanTemplate',
            'application.applicant',
            'application.district',
        ])->orderBy('bill_id');

        if ($schemeIdFilter !== null) {
            $billsQuery->whereHas('application', fn ($q) => $q->where('scheme_id', $schemeIdFilter));
        }

        $bills = $billsQuery->get();

        $validSerialCount = 0;
        $paidSkipped = [];
        $cancelledSkipped = [];
        $otherSkipped = [];
        $pendingCandidates = [];

        foreach ($bills as $bill) {
            if (self::isSerialFormatBill($bill)) {
                $validSerialCount++;
                continue;
            }

            if ($bill->status === 'paid') {
                $paidSkipped[] = $bill;
                continue;
            }

            if ($bill->status === 'cancelled') {
                $cancelledSkipped[] = $bill;
                continue;
            }

            if ($bill->status === 'pending') {
                $app = $bill->application;
                if (! $app) {
                    $otherSkipped[] = [$bill, 'missing_application'];
                    continue;
                }
                if ($app->isRejected() || $app->current_stage === Application::STAGE_DORMANT) {
                    $otherSkipped[] = [$bill, "stage_{$app->current_stage}"];
                    continue;
                }
                if ($app->bills()->where('status', 'paid')->exists()) {
                    $otherSkipped[] = [$bill, 'application_already_has_paid_bill'];
                    continue;
                }

                $pendingCandidates[] = $bill;
            }
        }

        $totalNonSerial = count($paidSkipped) + count($cancelledSkipped) + count($otherSkipped) + count($pendingCandidates);

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Bills inspected: {$bills->count()} | Already serial format: {$validSerialCount} | Non-serial total: {$totalNonSerial}");
        $this->line('Breakdown -> Pending eligible: ' . count($pendingCandidates) . ' | Paid (skipped): ' . count($paidSkipped) . ' | Cancelled (skipped): ' . count($cancelledSkipped) . ' | Other skipped: ' . count($otherSkipped));

        if ($dryRun) {
            $previewRows = [];
            foreach ($pendingCandidates as $bill) {
                $previewRows[] = [
                    $bill->application?->application_no ?? '—',
                    $bill->bill_id,
                    'pending',
                    (int) ceil((float) $bill->bill_amount),
                    $bill->psid,
                    '(WILL REGENERATE IN SERIAL FORMAT)',
                    'Eligible pending challan',
                ];
            }
            foreach ($otherSkipped as [$bill, $reason]) {
                $previewRows[] = [
                    $bill->application?->application_no ?? '—',
                    $bill->bill_id,
                    $bill->status,
                    (int) ceil((float) $bill->bill_amount),
                    $bill->psid,
                    '(SKIPPED)',
                    $reason,
                ];
            }
            foreach ($paidSkipped as $bill) {
                $previewRows[] = [
                    $bill->application?->application_no ?? '—',
                    $bill->bill_id,
                    'paid',
                    (int) ceil((float) $bill->bill_amount),
                    $bill->psid,
                    '(UNCHANGED)',
                    'Paid bill preserved',
                ];
            }
            foreach ($cancelledSkipped as $bill) {
                $previewRows[] = [
                    $bill->application?->application_no ?? '—',
                    $bill->bill_id,
                    'cancelled',
                    (int) ceil((float) $bill->bill_amount),
                    $bill->psid,
                    '(UNCHANGED)',
                    'Cancelled bill preserved',
                ];
            }

            $this->table(
                ['Application No', 'Bill ID', 'Bill Status', 'Amount', 'Old PSID', 'Action / New PSID', 'Reason'],
                $previewRows
            );

            return Command::SUCCESS;
        }

        if (empty($pendingCandidates)) {
            $this->info('No eligible pending non-serial bills found to regenerate.');
            return Command::SUCCESS;
        }

        $pdfDir = storage_path('app/regenerated_challans');
        if (! $skipPdf && ! is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $regeneratedRows = [];

        foreach ($pendingCandidates as $oldBill) {
            $oldBill->refresh();
            if ($oldBill->status !== 'pending') {
                // Already cancelled when another pending bill on the same application was regenerated
                continue;
            }

            $application = $oldBill->application()->with(['scheme.department', 'scheme.challanTemplate', 'applicant', 'district'])->first();
            if (! $application || $application->bills()->where('status', 'paid')->exists()) {
                continue;
            }

            $oldPsid = (string) $oldBill->psid;
            $amount = (float) $oldBill->bill_amount;

            $breakdown = $application->getChallanBreakdown();
            $template = $breakdown['template'];
            $validityDays = $overrideDays ?? (int) ($template->validity_days ?: 14);

            /** @var Bill $newBill */
            $newBill = DB::transaction(function () use ($application, $amount, $validityDays, $oldPsid, $gbpayService) {
                $application->challan_issued_at = now();
                if (! $application->challan_due_date || $application->challan_due_date->format('Y-m-d') <= now()->toDateString()) {
                    $application->challan_due_date = now()->addDays($validityDays)->toDateString();
                }
                if (! $application->challan_no) {
                    $application->challan_no = 'CHL-' . now()->format('Y') . '-' . str_pad((string) $application->id, 6, '0', STR_PAD_LEFT);
                }
                if (! $application->challan_amount && $amount > 0) {
                    $application->challan_amount = $amount;
                }
                $application->saveQuietly();

                $bill = $gbpayService->createOrUpdateBillForApplication($application, $amount > 0 ? $amount : null, true);

                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'from_status' => $application->current_stage ?: $application->status,
                    'to_status' => $application->current_stage ?: $application->status,
                    'note' => "PSID regenerated in serial format: {$oldPsid} -> {$bill->psid} (Serial #{$bill->voucher_serial}). Challan refreshed for citizen download.",
                ]);

                AccessAuditLog::log('gbpay_psid_regenerated_serial', Application::class, (string) $application->id, [
                    'application_no' => $application->application_no,
                    'old_psid' => $oldPsid,
                    'new_psid' => $bill->psid,
                    'voucher_serial' => $bill->voucher_serial,
                    'amount' => $bill->bill_amount,
                    'challan_no' => $application->challan_no,
                ]);

                return $bill;
            });

            $application->refresh();
            $pdfPath = null;
            if (! $skipPdf) {
                $psidConfig = PsidConfig::getActive($application->scheme_id);
                $qrCodeBase64 = QrCodeGenerator::toBase64Png($newBill->psid, 3, 2);

                $pdf = Pdf::loadView('public.applications.challan-pdf', [
                    'application' => $application,
                    'bill' => $newBill,
                    'psidConfig' => $psidConfig,
                    'qrCodeBase64' => $qrCodeBase64,
                    'scheme' => $application->scheme,
                    'applicant' => $application->applicant,
                    'district' => $application->district,
                    'template' => $template,
                    'breakdown' => $application->getChallanBreakdown(),
                ])->setPaper('a4', 'landscape')
                    ->setOption([
                        'isHtml5ParserEnabled' => true,
                        'isRemoteEnabled' => false,
                        'dpi' => 96,
                        'defaultFont' => 'DejaVu Sans',
                    ]);

                $pdfFilename = 'Challan-' . $application->challan_no . '.pdf';
                $pdfPath = $pdfDir . DIRECTORY_SEPARATOR . $pdfFilename;
                file_put_contents($pdfPath, $pdf->output());
            }

            $regeneratedRows[] = [
                'application_no' => $application->application_no,
                'applicant' => $application->applicant?->full_name ?? '—',
                'old_psid' => $oldPsid,
                'new_psid' => $newBill->psid,
                'voucher_serial' => $newBill->voucher_serial,
                'challan_no' => $application->challan_no,
                'amount_pkr' => (int) ceil((float) $newBill->bill_amount),
                'due_date' => $application->challan_due_date?->format('Y-m-d') ?? '',
                'challan_pdf' => $pdfPath ?? '(dynamic on /track)',
            ];
        }

        $csvFilename = 'gbpay_regenerated_psids_' . now()->format('Ymd_His') . '.csv';
        $csvPath = storage_path('app/' . $csvFilename);
        if (! is_dir(dirname($csvPath))) {
            mkdir(dirname($csvPath), 0755, true);
        }
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['Application No', 'Applicant', 'Old PSID', 'New PSID', 'Voucher Serial', 'Challan No', 'Amount PKR', 'Due Date', 'Challan PDF']);
        foreach ($regeneratedRows as $r) {
            fputcsv($fp, array_values($r));
        }
        fclose($fp);

        $this->table(
            ['Application No', 'Old PSID', 'New PSID', 'Serial No', 'Challan No', 'Amount (PKR)', 'Due Date'],
            array_map(fn ($r) => [
                $r['application_no'],
                $r['old_psid'],
                $r['new_psid'],
                $r['voucher_serial'],
                $r['challan_no'],
                $r['amount_pkr'],
                $r['due_date'],
            ], $regeneratedRows)
        );

        foreach ($regeneratedRows as $r) {
            $this->line("{$r['application_no']} | Old PSID: {$r['old_psid']} | New PSID: {$r['new_psid']} | Serial: #{$r['voucher_serial']} | Challan: {$r['challan_no']}");
        }

        $this->info('Regenerated ' . count($regeneratedRows) . ' pending PSID(s) & challan(s).');
        $this->info("Manifest CSV saved to: {$csvPath}");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Exports;

use App\Services\WheatSeedMisService;
use App\Support\Cnic;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WheatSeedBeneficiariesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithCustomStartCell, WithEvents
{
    private WheatSeedMisService $service;
    private string $generatedAt;
    private string $generatedBy;

    public function __construct(
        private Collection $applications,
        private bool $maskPii = false,
        ?string $generatedAt = null,
        ?string $generatedBy = null
    ) {
        $this->service = app(WheatSeedMisService::class);
        $this->generatedAt = $generatedAt ?? now()->format('d M Y, h:i A');
        $this->generatedBy = $generatedBy ?? (auth()->user()?->name ?? 'System Officer');
    }

    public function collection(): Collection
    {
        return $this->applications;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function headings(): array
    {
        return [
            'Application No',
            'Applicant Name',
            'Father / Husband Name',
            'CNIC',
            'Mobile Phone',
            'Division',
            'District',
            'Tehsil',
            'Union Council',
            'Village',
            'Land Location',
            'Available Land (Kanals)',
            'Available Land (Acres)',
            'Cultivated Wheat Area Last Year (Kanals)',
            'Requested Bags (50kg)',
            'Approved Bags',
            'Disbursed Bags',
            'Total Weight (kg)',
            'Total Weight (Metric Tons)',
            'Water Availability',
            'Road Access',
            'Survey / Field Status',
            'Application Status',
            'Reviewing Officer',
            'Submitted At',
        ];
    }

    public function map($app): array
    {
        $applicant = $app->applicant;
        $parsed = $this->service->extractAppAttributes($app);

        $cnic = $this->maskPii ? Cnic::mask($applicant?->cnic) : Cnic::dashed($applicant?->cnic);
        $phone = $this->maskPii ? Cnic::maskPhone($applicant?->phone) : ($applicant?->phone ?? '—');

        $latestLog = $app->statusLogs?->first();

        $approvedBags = in_array($app->status, ['directorate_approved', 'disbursed'], true) ? $parsed['requested_bags'] : 0;
        $disbursedBags = $app->status === 'disbursed' ? $parsed['requested_bags'] : 0;

        $weightKg = $parsed['requested_bags'] * $this->service->getConfiguredBagWeight();
        $weightMt = round($weightKg / 1000, 2);

        $surveyStatus = (in_array($app->status, ['district_verified', 'directorate_approved', 'disbursed'], true) || ! empty($app->verification_note))
            ? 'Verified / Surveyed'
            : 'Pending Survey';

        return [
            $app->application_no,
            $applicant?->full_name ?? '—',
            $applicant?->father_name ?? '—',
            $cnic,
            $phone,
            WheatSeedMisService::divisionForDistrict($app->district?->name),
            $app->district?->name ?? '—',
            $applicant?->tehsil ?: '—',
            $applicant?->union_council ?: '—',
            $applicant?->village ?: '—',
            $applicant?->land_location ?: '—',
            $parsed['land_kanals'] !== null ? $parsed['land_kanals'] : '—',
            $parsed['land_acres'] !== null ? $parsed['land_acres'] : '—',
            $parsed['cultivated_kanals'] !== null ? $parsed['cultivated_kanals'] : 'Not yet captured',
            $parsed['requested_bags'],
            $approvedBags,
            $disbursedBags,
            $weightKg,
            $weightMt,
            $parsed['water_available'] ?? 'Not yet captured',
            $parsed['road_access'] ?? 'Not yet captured',
            $surveyStatus,
            $app->statusLabel(),
            $latestLog?->user?->name ?? '—',
            optional($app->submitted_at ?? $app->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'Y'; // 25 columns (A to Y)

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->mergeCells("A3:{$lastCol}3");

                $sheet->setCellValue('A1', 'Agriculture Department Gilgit Baltistan — Online Portal');
                $sheet->setCellValue('A2', 'Wheat Seed (50:50 Cost Sharing) Scheme Beneficiary Registry Report');
                $sheet->setCellValue(
                    'A3',
                    'Report Generated: ' . $this->generatedAt . ' | Generated By: ' . $this->generatedBy . ' | Total Records: ' . $this->applications->count()
                );

                // Title row styling
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Subtitle row styling
                $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E293B']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Metadata row styling
                $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Table headings row styling
                $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(2)->setRowHeight(22);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(24);
            },
        ];
    }
}

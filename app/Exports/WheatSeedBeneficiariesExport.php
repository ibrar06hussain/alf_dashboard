<?php

namespace App\Exports;

use App\Services\WheatSeedMisService;
use App\Support\Cnic;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WheatSeedBeneficiariesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    private WheatSeedMisService $service;

    public function __construct(
        private Collection $applications,
        private bool $maskPii = true
    ) {
        $this->service = app(WheatSeedMisService::class);
    }

    public function collection(): Collection
    {
        return $this->applications;
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

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

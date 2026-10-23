<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApplicationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private Collection $applications,
        private bool $masked = false
    ) {
    }

    public function collection(): Collection
    {
        return $this->applications;
    }

    public function headings(): array
    {
        return [
            'Application No',
            'Applicant',
            'CNIC',
            'Phone',
            'District',
            'Tehsil',
            'Union Council',
            'Village',
            'Department',
            'Scheme',
            'Status',
            'Submitted At',
            'Created At',
        ];
    }

    public function map($application): array
    {
        $cnic = $application->applicant?->cnic;
        $phone = $application->applicant?->phone;

        if ($this->masked) {
            if ($cnic && strlen($cnic) >= 13) {
                $raw = str_replace('-', '', $cnic);
                $cnic = substr($raw, 0, 5).'-******-'.substr($raw, -1);
            }
            if ($phone && strlen($phone) >= 10) {
                $phone = substr($phone, 0, 4).'***'.substr($phone, -4);
            }
        }

        return [
            $application->application_no,
            $application->applicant?->full_name,
            $cnic,
            $phone,
            $application->district?->name,
            $application->applicant?->tehsil,
            $application->applicant?->union_council,
            $application->applicant?->village,
            $application->scheme?->department?->name,
            $application->scheme?->title,
            $application->status,
            optional($application->submitted_at)?->format('Y-m-d H:i'),
            optional($application->created_at)?->format('Y-m-d H:i'),
        ];
    }
}

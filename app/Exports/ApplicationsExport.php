<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApplicationsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Collection $applications)
    {
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
            'Department',
            'Scheme',
            'Status',
            'Submitted At',
            'Created At',
        ];
    }

    public function map($application): array
    {
        return [
            $application->application_no,
            $application->applicant?->full_name,
            $application->applicant?->cnic,
            $application->applicant?->phone,
            $application->district?->name,
            $application->scheme?->department?->name,
            $application->scheme?->title,
            $application->status,
            optional($application->submitted_at)?->format('Y-m-d H:i'),
            optional($application->created_at)?->format('Y-m-d H:i'),
        ];
    }
}

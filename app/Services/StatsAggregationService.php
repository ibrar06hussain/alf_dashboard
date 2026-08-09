<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DepartmentStat;
use Illuminate\Support\Collection;

class StatsAggregationService
{
    public const METRICS = [
        'applications_total' => 'Total applications (all statuses)',
        'applications_pending' => 'Pending applications',
        'applications_approved' => 'Directorate approved + disbursed',
        'applications_disbursed' => 'Disbursed applications',
        'applications_rejected' => 'Rejected applications',
        'applicants_unique' => 'Unique applicants (by CNIC)',
    ];

    public function refreshAll(): int
    {
        $stats = DepartmentStat::query()
            ->where('source', 'auto')
            ->whereNotNull('auto_metric')
            ->get();

        foreach ($stats as $stat) {
            $stat->update(['stat_value' => (string) $this->compute($stat->department_id, $stat->auto_metric)]);
        }

        return $stats->count();
    }

    public function compute(int $departmentId, string $metric): int
    {
        $base = Application::query()->whereHas('scheme', fn ($q) => $q->where('department_id', $departmentId));

        return match ($metric) {
            'applications_total' => (clone $base)->count(),
            'applications_pending' => (clone $base)->where('status', 'pending')->count(),
            'applications_approved' => (clone $base)->whereIn('status', ['directorate_approved', 'disbursed'])->count(),
            'applications_disbursed' => (clone $base)->where('status', 'disbursed')->count(),
            'applications_rejected' => (clone $base)->where('status', 'rejected')->count(),
            'applicants_unique' => (clone $base)->distinct('applicant_id')->count('applicant_id'),
            default => 0,
        };
    }

    public function metricOptions(): Collection
    {
        return collect(self::METRICS);
    }
}

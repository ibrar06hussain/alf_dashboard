<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Scheme;
use App\Models\SchemeDistrict;

class QuotaService
{
    /**
     * Count approved slots used against a district quota.
     * Approvals that consume quota: directorate_approved + disbursed.
     */
    public function usedSlots(int $schemeId, int $districtId): int
    {
        return Application::query()
            ->where('scheme_id', $schemeId)
            ->where('district_id', $districtId)
            ->whereIn('status', ['directorate_approved', 'disbursed'])
            ->count();
    }

    public function quotaFor(Scheme $scheme, int $districtId): ?int
    {
        if ($scheme->scope !== 'district') {
            return null;
        }

        $row = SchemeDistrict::query()
            ->where('scheme_id', $scheme->id)
            ->where('district_id', $districtId)
            ->first();

        return $row?->quota;
    }

    /**
     * Returns null if no quota applies, otherwise ['quota'=>n,'used'=>n,'remaining'=>n].
     */
    public function status(Scheme $scheme, int $districtId): ?array
    {
        $quota = $this->quotaFor($scheme, $districtId);
        if ($quota === null) {
            return null;
        }

        $used = $this->usedSlots($scheme->id, $districtId);

        return [
            'quota' => $quota,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'exceeded' => $used >= $quota,
        ];
    }

    public function canApprove(Scheme $scheme, int $districtId): bool
    {
        $status = $this->status($scheme, $districtId);
        if ($status === null) {
            return true;
        }

        return ! $status['exceeded'];
    }
}

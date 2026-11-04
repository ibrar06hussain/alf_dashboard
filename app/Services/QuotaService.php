<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Scheme;
use App\Models\SchemeDistrict;

class QuotaService
{
    /**
     * Count approved bags / quantity used against a district quota.
     * Approvals that consume quota: directorate_approved + disbursed,
     * or modern lifecycle stages where application is approved/allocated/distributed.
     */
    public function usedSlots(int $schemeId, int $districtId): int
    {
        $applications = Application::with('fieldValues.formField')
            ->where('scheme_id', $schemeId)
            ->where('district_id', $districtId)
            ->where(function ($q) {
                $q->whereIn('status', [Application::STATUS_DIRECTORATE_APPROVED, Application::STATUS_DISBURSED])
                  ->orWhereIn('current_stage', [
                      Application::STAGE_APPROVED,
                      Application::STAGE_CHALLAN_ISSUED,
                      Application::STAGE_PAYMENT_PROOF_SUBMITTED,
                      Application::STAGE_PAYMENT_VERIFIED,
                      Application::STAGE_ELIGIBLE_BENEFICIARY,
                      Application::STAGE_STOCK_ALLOCATED,
                      Application::STAGE_PARTIALLY_DISTRIBUTED,
                      Application::STAGE_FULLY_DISTRIBUTED,
                      Application::STAGE_COMPLETED,
                      Application::STAGE_DISBURSED,
                  ]);
            })
            ->get();

        $totalApprovedBags = 0;
        foreach ($applications as $app) {
            $approvedQty = $app->getApprovedQuantity();
            if ($approvedQty !== null && $approvedQty > 0) {
                $totalApprovedBags += (int) ceil((float) $approvedQty);
            } else {
                $demanded = (int) ceil((float) $app->getDemandedQuantity());
                $totalApprovedBags += ($demanded > 0 ? $demanded : 1);
            }
        }

        return $totalApprovedBags;
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

    public function canApprove(Scheme $scheme, int $districtId, ?float $quantityToApprove = null): bool
    {
        $status = $this->status($scheme, $districtId);
        if ($status === null) {
            return true;
        }

        if ($quantityToApprove !== null && $quantityToApprove > 0) {
            return ($status['used'] + $quantityToApprove) <= $status['quota'];
        }

        return ! $status['exceeded'];
    }
}

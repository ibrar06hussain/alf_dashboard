<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine whether the user can view any applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGlobalScope() || $user->hasRole('Super Admin') || $user->can('applications.view');
    }

    /**
     * Determine whether the user can view the specific application.
     */
    public function view(User $user, Application $application): bool
    {
        // Global oversight (Secretary, Super Admin)
        if ($user->isGlobalScope() || $user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can('applications.view') && ! $user->hasAnyRole([
            'Division Director', 'District Deputy Director', 'District Officer',
            'Field Officer', 'Director Agriculture', 'Director Livestock', 'Director Fisheries',
        ])) {
            return false;
        }

        // Field officers: strictly limited to applications with tasks or surveys assigned to them
        if ($user->hasRole('Field Officer') && ! $user->hasRole('Super Admin')) {
            $hasAssignment = $application->assignments()
                ->where('assigned_to', $user->id)
                ->whereIn('status', ['assigned', 'in_progress', 'completed'])
                ->exists();

            $hasSurvey = $application->surveys()
                ->where('assigned_to', $user->id)
                ->exists();

            return $hasAssignment || $hasSurvey;
        }

        // Division Directors: authorized within their division's districts
        if ($user->hasRole('Division Director')) {
            return in_array((int) $application->district_id, $user->authorizedDistrictIds(), true);
        }

        // District Deputy Directors, Accounts, Distribution: match both district and department
        $districtOk = in_array($application->district_id, $user->authorizedDistrictIds(), true);
        $deptOk = in_array($application->scheme->department_id, $user->authorizedDepartmentIds(), true);

        return $districtOk && $deptOk;
    }

    /**
     * Determine whether the user can perform district-level verification.
     */
    public function verifyDistrict(User $user, Application $application): bool
    {
        // Technical Super Admin and Secretary have zero beneficiary approval authority
        if ($user->hasAnyRole(['Super Admin', 'Secretary'])) {
            return false;
        }

        if (! $user->can('applications.verify_district')) {
            return false;
        }

        if ($application->status !== 'pending') {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can approve at the directorate level.
     */
    public function approveDirectorate(User $user, Application $application): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary'])) {
            return false;
        }

        if (! $user->can('applications.approve_directorate')) {
            return false;
        }

        if ($application->status !== 'district_verified') {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can reject the application.
     */
    public function reject(User $user, Application $application): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary'])) {
            return false;
        }

        if (! $user->can('applications.reject')) {
            return false;
        }

        if (in_array($application->status, ['rejected', 'disbursed'], true)) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can assign a Field Officer or survey to this application.
     */
    public function assignFieldOfficer(User $user, Application $application): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Field Officer'])) {
            return false;
        }

        if (! $user->can('applications.assign_field_officer')) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Alias for assignFieldOfficer
     */
    public function assignSurvey(User $user, Application $application): bool
    {
        return $this->assignFieldOfficer($user, $application);
    }

    /**
     * Determine whether the user can request correction from citizen.
     */
    public function requestCorrection(User $user, Application $application): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary', 'Field Officer'])) {
            return false;
        }

        if (! $user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can verify payments.
     */
    public function verifyPayment(User $user, Application $application): bool
    {
        if (! $user->can('payments.verify') && ! $user->hasRole('Accounts Officer')) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can allocate stock / items.
     */
    public function allocateStock(User $user, Application $application): bool
    {
        if (! ($user->can('distribution.allocate_stock') || $user->hasAnyRole(['Distribution Officer', 'District Deputy Director', 'District Officer']))) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can reactivate a dormant application.
     */
    public function reactivate(User $user, Application $application): bool
    {
        if (! $user->hasAnyRole(['District Deputy Director', 'District Officer'])) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether the user can disburse / confirm delivery.
     */
    public function disburse(User $user, Application $application): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Secretary'])) {
            return false;
        }

        if (! ($user->can('distribution.confirm_delivery') || $user->can('applications.approve_directorate'))) {
            return false;
        }

        if (! in_array($application->status, ['directorate_approved', 'approved', 'stock_allocated'], true)
            && ! in_array($application->current_stage, [Application::STAGE_APPROVED, Application::STAGE_STOCK_ALLOCATED, Application::STAGE_PARTIALLY_DISTRIBUTED], true)) {
            return false;
        }

        return $this->isInOperationalScope($user, $application);
    }

    /**
     * Determine whether user is within authorized operational scope (district + department).
     */
    private function isInOperationalScope(User $user, Application $application): bool
    {
        $authorizedDistricts = $user->authorizedDistrictIds();
        $districtOk = empty($authorizedDistricts) || in_array($application->district_id, $authorizedDistricts, true);

        $authorizedDepts = $user->authorizedDepartmentIds();
        $deptOk = empty($authorizedDepts) || in_array($application->scheme->department_id, $authorizedDepts, true);

        return $districtOk && $deptOk;
    }
}

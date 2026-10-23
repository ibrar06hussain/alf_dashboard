<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ScopeByOfficeAssignment
{
    /**
     * Scope query to records authorized for the given user's office assignment and role.
     */
    public function scopeAuthorizedFor(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Field Officers: strictly limited to applications with assigned tasks
        if ($user->hasRole('Field Officer') && ! $user->hasRole('Super Admin')) {
            return $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->whereIn('status', ['assigned', 'in_progress', 'completed']);
            });
        }

        // Global users (Secretary, Super Admin) can view all
        if ($user->isGlobalScope()) {
            return $query;
        }

        // Division Directors: Scoped to the districts in their assigned division(s)
        if ($user->hasRole('Division Director')) {
            $divisionDistricts = $user->authorizedDistrictIds();

            return $query->whereIn('district_id', $divisionDistricts);
        }

        // District Deputy Directors / District Officers / Accounts / Distribution
        $districtIds = $user->authorizedDistrictIds();
        $departmentIds = $user->authorizedDepartmentIds();

        if (! empty($districtIds)) {
            $query->whereIn('district_id', $districtIds);
        }

        if (! empty($departmentIds)) {
            $query->whereHas('scheme', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        return $query;
    }
}

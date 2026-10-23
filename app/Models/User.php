<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'district_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function statusLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class);
    }

    public function officeAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserOfficeAssignment::class);
    }

    public function activeOfficeAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->officeAssignments()->active();
    }

    public function applicationAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ApplicationAssignment::class, 'assigned_to');
    }

    public function isGlobalScope(): bool
    {
        return $this->hasAnyRole(['Super Admin', 'Secretary']);
    }

    /**
     * Get authorized division IDs for the user.
     */
    public function authorizedDivisionIds(): array
    {
        if ($this->isGlobalScope()) {
            return Division::pluck('id')->all();
        }

        $activeAssignments = $this->activeOfficeAssignments()->get();

        $divisionIds = $activeAssignments->pluck('division_id')->filter()->unique()->values()->all();

        if (empty($divisionIds)) {
            // Check if district assignments belong to divisions
            $districtIds = $this->authorizedDistrictIds();
            if (! empty($districtIds)) {
                $divisionIds = \Illuminate\Support\Facades\DB::table('division_districts')
                    ->whereIn('district_id', $districtIds)
                    ->pluck('division_id')
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return array_values(array_unique(array_map('intval', $divisionIds)));
    }

    /**
     * Get authorized district IDs for the user.
     */
    public function authorizedDistrictIds(): array
    {
        if ($this->isGlobalScope()) {
            return array_map('intval', District::pluck('id')->all());
        }

        $activeAssignments = $this->activeOfficeAssignments()->get();

        // If user is a Division Director or assigned to a division
        if ($this->hasRole('Division Director') || $activeAssignments->contains(fn ($a) => $a->division_id && ! $a->district_id)) {
            $divIds = $activeAssignments->pluck('division_id')->filter()->all();
            if (! empty($divIds)) {
                return \Illuminate\Support\Facades\DB::table('division_districts')
                    ->whereIn('division_id', $divIds)
                    ->pluck('district_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        $assignedDistrictIds = $activeAssignments->pluck('district_id')->filter()->all();

        if ($this->district_id) {
            $assignedDistrictIds[] = $this->district_id;
        }

        return array_values(array_unique(array_map('intval', $assignedDistrictIds)));
    }

    /**
     * Get authorized department IDs for the user.
     */
    public function authorizedDepartmentIds(): array
    {
        if ($this->isGlobalScope() || $this->hasRole('Division Director')) {
            return array_map('intval', Department::pluck('id')->all());
        }

        $activeAssignments = $this->activeOfficeAssignments()->get();
        $deptIds = $activeAssignments->pluck('department_id')->filter()->all();

        if ($this->department_id) {
            $deptIds[] = $this->department_id;
        }

        return array_values(array_unique(array_map('intval', $deptIds)));
    }

    /**
     * Can the user approve or reject beneficiary applications?
     * Note: Super Admin and Secretary explicitly cannot approve beneficiary applications.
     */
    public function canApproveApplications(): bool
    {
        if ($this->hasAnyRole(['Super Admin', 'Secretary'])) {
            return false;
        }

        return $this->can('applications.approve_directorate') || $this->can('applications.verify_district');
    }

    /**
     * Can the user assign field officers for on-site surveys?
     */
    public function canAssignFieldOfficers(): bool
    {
        return $this->can('applications.assign_field_officer');
    }
}

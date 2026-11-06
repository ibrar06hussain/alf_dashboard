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
     * Determine if the user has provincial / director-level oversight (Directors, Secretary, Super Admin).
     */
    public function isDirectorSecretaryOrAdmin(): bool
    {
        return $this->isGlobalScope() || $this->hasAnyRole([
            'Director Agriculture',
            'Director Livestock',
            'Director Fisheries',
            'Division Director',
        ]);
    }

    /**
     * Determine if the user is an officer other than Directors, Secretary, and Admin (district-level scoped officer).
     */
    public function isDistrictScopedOfficer(): bool
    {
        return ! $this->isDirectorSecretaryOrAdmin();
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

        // Also check if district assignments belong to divisions
        $districtIds = $this->authorizedDistrictIds();
        if (! empty($districtIds)) {
            $mappedDivisionIds = \Illuminate\Support\Facades\DB::table('division_districts')
                ->whereIn('district_id', $districtIds)
                ->pluck('division_id')
                ->unique()
                ->values()
                ->all();
            $divisionIds = array_merge($divisionIds, $mappedDivisionIds);
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
        $districtIds = [];

        // 1. Districts from division-wide assignments (division_id is set and district_id is null, or Division Director)
        $divisionScopeAssignments = $activeAssignments->filter(function ($a) {
            return $a->division_id && ! $a->district_id;
        });

        $divIds = $divisionScopeAssignments->pluck('division_id')->filter()->unique()->all();
        if ($this->hasRole('Division Director')) {
            $divIds = array_unique(array_merge($divIds, $activeAssignments->pluck('division_id')->filter()->all()));
        }

        if (! empty($divIds)) {
            $divDistrictIds = \Illuminate\Support\Facades\DB::table('division_districts')
                ->whereIn('division_id', $divIds)
                ->pluck('district_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $districtIds = array_merge($districtIds, $divDistrictIds);
        }

        // 2. Districts from explicit district assignments
        $assignedDistrictIds = $activeAssignments->pluck('district_id')->filter()->map(fn ($id) => (int) $id)->all();
        $districtIds = array_merge($districtIds, $assignedDistrictIds);

        // 3. User direct district_id
        if ($this->district_id) {
            $districtIds[] = (int) $this->district_id;
        }

        $districtIds = array_values(array_unique(array_filter($districtIds)));

        // 4. Fallback for Director roles with NO explicit district or division assignments -> province-wide (all districts)
        if (empty($districtIds) && $this->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            return array_map('intval', District::pluck('id')->all());
        }

        return $districtIds;
    }

    /**
     * Get authorized department IDs for the user.
     */
    public function authorizedDepartmentIds(): array
    {
        if ($this->isGlobalScope()) {
            return array_map('intval', Department::pluck('id')->all());
        }

        $activeAssignments = $this->activeOfficeAssignments()->get();
        $deptIds = $activeAssignments->pluck('department_id')->filter()->map(fn ($id) => (int) $id)->all();

        if ($this->department_id) {
            $deptIds[] = (int) $this->department_id;
        }

        // Infer from role names if none explicitly set in assignments
        if (empty($deptIds)) {
            if ($this->hasRole('Director Agriculture')) {
                $agri = Department::where('slug', 'agriculture')->value('id');
                if ($agri) {
                    $deptIds[] = (int) $agri;
                }
            } elseif ($this->hasRole('Director Livestock')) {
                $live = Department::where('slug', 'livestock')->value('id');
                if ($live) {
                    $deptIds[] = (int) $live;
                }
            } elseif ($this->hasRole('Director Fisheries')) {
                $fish = Department::where('slug', 'fisheries')->value('id');
                if ($fish) {
                    $deptIds[] = (int) $fish;
                }
            }
        }

        $deptIds = array_values(array_unique(array_filter($deptIds)));

        // If user has NO department restriction specified, they oversee all departments in their jurisdiction
        if (empty($deptIds)) {
            return array_map('intval', Department::pluck('id')->all());
        }

        return $deptIds;
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

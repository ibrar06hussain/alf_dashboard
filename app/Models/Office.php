<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    public const TYPE_SECRETARIAT = 'secretariat';
    public const TYPE_DIVISION_DIRECTORATE = 'division_directorate';
    public const TYPE_DISTRICT_DEPUTY_DIRECTORATE = 'district_deputy_directorate';
    public const TYPE_FIELD_OFFICE = 'field_office';
    public const TYPE_ACCOUNTS_UNIT = 'accounts_unit';
    public const TYPE_DISTRIBUTION_DEPOT = 'distribution_depot';

    public const TYPES = [
        self::TYPE_SECRETARIAT => 'Secretariat',
        self::TYPE_DIVISION_DIRECTORATE => 'Division Directorate',
        self::TYPE_DISTRICT_DEPUTY_DIRECTORATE => 'District Deputy Directorate',
        self::TYPE_FIELD_OFFICE => 'Field Office',
        self::TYPE_ACCOUNTS_UNIT => 'Accounts Unit',
        self::TYPE_DISTRIBUTION_DEPOT => 'Distribution Depot',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'office_type',
        'department_id',
        'division_id',
        'district_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserOfficeAssignment::class);
    }
}

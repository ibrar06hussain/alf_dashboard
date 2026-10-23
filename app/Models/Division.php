<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    protected $fillable = [
        'name',
        'code',
        'headquarters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'division_districts')
            ->withTimestamps();
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserOfficeAssignment::class);
    }
}

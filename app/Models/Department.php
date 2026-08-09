<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name', 'slug', 'vision', 'mission', 'objectives', 'leadership_info',
        'icon', 'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(Scheme::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(DepartmentStat::class)->orderBy('display_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

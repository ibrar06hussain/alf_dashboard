<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasLocalizedFields;

    protected $fillable = [
        'name', 'name_ur', 'slug', 'vision', 'vision_ur', 'mission', 'mission_ur',
        'objectives', 'objectives_ur', 'leadership_info', 'leadership_info_ur',
        'icon', 'cover_image', 'is_active', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function coverUrl(string $fallback = 'frontend/img/bg-img/2.jpg'): string
    {
        if ($this->cover_image) {
            return asset('storage/'.$this->cover_image);
        }

        return \App\Models\SiteSetting::imageUrl('default_breadcrumb', $fallback);
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

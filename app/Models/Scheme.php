<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Scheme extends Model
{
    protected $fillable = [
        'department_id', 'title', 'slug', 'type', 'scope', 'budget',
        'start_date', 'last_date', 'status', 'description',
        'eligibility_criteria', 'terms_conditions', 'featured_image', 'is_featured',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'last_date' => 'date',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function imageUrl(?int $fallbackIndex = null): string
    {
        if ($this->featured_image) {
            return asset('storage/'.$this->featured_image);
        }

        $n = $fallbackIndex ?? (($this->id % 5) + 8);

        return asset('frontend/img/bg-img/'.$n.'.jpg');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'scheme_districts')
            ->withPivot('quota')
            ->withTimestamps();
    }

    public function schemeDistricts(): HasMany
    {
        return $this->hasMany(SchemeDistrict::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function publishedForm(): HasOne
    {
        return $this->hasOne(Form::class)->where('is_published', true)->latestOfMany();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(SchemeInterest::class);
    }

    public function isOpenForApplications(): bool
    {
        if ($this->type !== 'running' || $this->status !== 'active') {
            return false;
        }

        if ($this->last_date && $this->last_date->copy()->endOfDay()->isPast()) {
            return false;
        }

        return $this->forms()->where('is_published', true)->exists();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'running' => 'On Going',
            'proposed' => 'Proposed',
            default => ucfirst((string) $this->type),
        };
    }

    public static function typeLabels(): array
    {
        return [
            'running' => 'On Going',
            'proposed' => 'Proposed',
        ];
    }
}

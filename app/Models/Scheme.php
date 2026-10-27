<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Scheme extends Model
{
    use HasLocalizedFields;

    protected $fillable = [
        'department_id', 'title', 'title_ur', 'slug', 'type', 'scope', 'budget',
        'start_date', 'last_date', 'status', 'description', 'description_ur',
        'eligibility_criteria', 'eligibility_criteria_ur',
        'terms_conditions', 'terms_conditions_ur',
        'featured_image', 'is_featured', 'display_order',
        'requires_challan', 'challan_fee_per_unit', 'challan_template_id',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'start_date' => 'date',
            'last_date' => 'date',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
            'requires_challan' => 'boolean',
            'challan_fee_per_unit' => 'decimal:2',
            'challan_template_id' => 'integer',
        ];
    }

    public function imageUrl(?int $fallbackIndex = null): string
    {
        if ($this->featured_image) {
            return asset('storage/'.$this->featured_image);
        }

        $n = $fallbackIndex ?? (($this->id % 5) + 8);

        return \App\Models\SiteSetting::imageUrl('default_breadcrumb', 'frontend/img/bg-img/'.$n.'.jpg');
    }

    public function bannerUrl(?int $fallbackIndex = null): string
    {
        return $this->imageUrl($fallbackIndex);
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

    public function stockItems(): HasMany
    {
        return $this->hasMany(SchemeStockItem::class);
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

    public function challanTemplate(): BelongsTo
    {
        return $this->belongsTo(ChallanTemplate::class, 'challan_template_id');
    }

    public function getChallanTemplate(): ChallanTemplate
    {
        return $this->challanTemplate ?? ChallanTemplate::getDefault();
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
            'running' => __('portal.type_running'),
            'proposed' => __('portal.type_proposed'),
            default => ucfirst((string) $this->type),
        };
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            'gb' => __('portal.scope_gb'),
            'district' => __('portal.scope_district'),
            default => ucfirst((string) $this->scope).' '.__('portal.scope'),
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

<?php

namespace App\Models;

use App\Models\Concerns\ScopeByOfficeAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use ScopeByOfficeAssignment;

    public const STATUSES = [
        'pending' => 'Pending / Under Review',
        'district_verified' => 'District Verified',
        'directorate_approved' => 'Directorate Approved',
        'disbursed' => 'Disbursed',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'application_no', 'scheme_id', 'applicant_id', 'district_id',
        'form_id', 'status', 'verification_note', 'rejection_reason', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function statusLabel(): string
    {
        return self::translateStatus((string) $this->status);
    }

    public static function translateStatus(string $status): string
    {
        return match ($status) {
            'pending' => __('portal.status_pending'),
            'district_verified' => __('portal.status_district_verified'),
            'directorate_approved' => __('portal.status_directorate_approved'),
            'disbursed' => __('portal.status_disbursed'),
            'rejected' => __('portal.status_rejected'),
            default => ucfirst($status),
        };
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ApplicationFieldValue::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ApplicationStatusLog::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ApplicationAssignment::class);
    }

    /**
     * Get available land in Kanals from dynamic form values.
     */
    public function getAvailableLandKanals(): ?float
    {
        $fieldValues = $this->relationLoaded('fieldValues')
            ? $this->fieldValues
            : $this->fieldValues()->with('formField')->get();

        $fv = $fieldValues->first(function ($v) {
            $key = $v->formField?->field_key;
            return in_array($key, ['land_kanals', 'available_land'], true) || $v->form_field_id === 8;
        });

        if ($fv && is_numeric(trim($fv->value))) {
            return (float) trim($fv->value);
        }

        return null;
    }

    /**
     * Get number of bags required (50kg each).
     * Uses explicit bags_required field if submitted; otherwise derives from wheat agronomic formula (1 bag/4 kanals).
     */
    public function getBagsRequired(): ?int
    {
        $fieldValues = $this->relationLoaded('fieldValues')
            ? $this->fieldValues
            : $this->fieldValues()->with('formField')->get();

        $fv = $fieldValues->first(function ($v) {
            $key = $v->formField?->field_key;
            return in_array($key, ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags'], true);
        });

        if ($fv && is_numeric(trim($fv->value)) && (int) $fv->value > 0) {
            return (int) $fv->value;
        }

        $land = $this->getAvailableLandKanals();
        if ($land !== null && $land > 0 && str_contains(strtolower($this->scheme?->title ?? ''), 'wheat')) {
            return (int) max(1, ceil($land / 4));
        }

        return null;
    }

    /**
     * Get previous year's cultivated land in Kanals.
     * Sourced from previous_cultivation form field.
     */
    public function getPreviousCultivatedLandKanals(): ?float
    {
        $fieldValues = $this->relationLoaded('fieldValues')
            ? $this->fieldValues
            : $this->fieldValues()->with('formField')->get();

        $fv = $fieldValues->first(function ($v) {
            $key = strtolower(trim($v->formField?->field_key ?? ''));
            $label = strtolower(trim($v->formField?->field_label ?? ''));
            return in_array($key, [
                'previous_cultivation',
                'previous_cultivated_area',
                'previous_cultivated_land',
                'wheat_cultivated_area',
                'cultivated_land',
                'wheat_area_last_year',
            ], true)
            || str_contains($key, 'previous_cultiv')
            || str_contains($label, 'previous cultiv')
            || str_contains($label, 'last year cultiv');
        });

        if ($fv && is_numeric(trim($fv->value))) {
            return (float) trim($fv->value);
        }

        return null;
    }

    public static function generateApplicationNo(): string
    {
        return 'ALF-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
    }
}

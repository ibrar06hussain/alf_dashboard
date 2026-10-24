<?php

namespace App\Models;

use App\Models\Concerns\ScopeByOfficeAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use ScopeByOfficeAssignment;

    // Legacy status constants (preserved for backward compatibility)
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISTRICT_VERIFIED = 'district_verified';
    public const STATUS_DIRECTORATE_APPROVED = 'directorate_approved';
    public const STATUS_DISBURSED = 'disbursed';
    public const STATUS_REJECTED = 'rejected';

    // Lifecycle Stages
    public const STAGE_SUBMITTED = 'submitted';
    public const STAGE_UNDER_REVIEW = 'under_review';
    public const STAGE_CORRECTION_REQUIRED = 'correction_required';
    public const STAGE_FIELD_SURVEY_ASSIGNED = 'field_survey_assigned';
    public const STAGE_VISIT_SCHEDULED = 'visit_scheduled';
    public const STAGE_VISIT_COMPLETED = 'visit_completed';
    public const STAGE_FINDINGS_SUBMITTED = 'findings_submitted';
    public const STAGE_APPROVED = 'approved';
    public const STAGE_CHALLAN_ISSUED = 'challan_issued';
    public const STAGE_PAYMENT_PROOF_SUBMITTED = 'payment_proof_submitted';
    public const STAGE_PAYMENT_VERIFIED = 'payment_verified';
    public const STAGE_ELIGIBLE_BENEFICIARY = 'eligible_beneficiary';
    public const STAGE_STOCK_ALLOCATED = 'stock_allocated';
    public const STAGE_PARTIALLY_DISTRIBUTED = 'partially_distributed';
    public const STAGE_FULLY_DISTRIBUTED = 'fully_distributed';
    public const STAGE_COMPLETED = 'completed';
    public const STAGE_REJECTED = 'rejected';
    public const STAGE_DORMANT = 'dormant';

    public const STATUSES = [
        // Modern lifecycle
        self::STAGE_SUBMITTED => 'Submitted / Received',
        self::STAGE_UNDER_REVIEW => 'DD Review',
        self::STAGE_CORRECTION_REQUIRED => 'Correction Required',
        self::STAGE_FIELD_SURVEY_ASSIGNED => 'Field Survey Assigned',
        self::STAGE_VISIT_SCHEDULED => 'Visit Scheduled',
        self::STAGE_VISIT_COMPLETED => 'Visit Completed',
        self::STAGE_FINDINGS_SUBMITTED => 'Findings Submitted',
        self::STAGE_APPROVED => 'Approved (DD Decision)',
        self::STAGE_CHALLAN_ISSUED => 'Challan Issued (Pending Payment)',
        self::STAGE_PAYMENT_PROOF_SUBMITTED => 'Payment Proof Submitted',
        self::STAGE_PAYMENT_VERIFIED => 'Payment Verified (Accounts Clearance)',
        self::STAGE_ELIGIBLE_BENEFICIARY => 'Eligible Beneficiary',
        self::STAGE_STOCK_ALLOCATED => 'Stock Allocated',
        self::STAGE_PARTIALLY_DISTRIBUTED => 'Partially Distributed',
        self::STAGE_FULLY_DISTRIBUTED => 'Fully Distributed',
        self::STAGE_COMPLETED => 'Completed',
        self::STAGE_REJECTED => 'Rejected',
        self::STAGE_DORMANT => 'Dormant (Unpaid Challan Expired)',

        // Legacy compatibility
        'pending' => 'Pending / Under Review',
        'district_verified' => 'District Verified',
        'directorate_approved' => 'Directorate Approved',
        'disbursed' => 'Disbursed',
    ];

    protected $fillable = [
        'application_no', 'scheme_id', 'applicant_id', 'district_id', 'division_id', 'department_id',
        'form_id', 'status', 'current_stage', 'verification_note', 'rejection_reason',
        'submitted_at', 'received_at', 'assigned_at', 'stage_updated_at', 'assigned_to',
        'responsible_office_id', 'is_unassigned',
        'correction_remarks', 'correction_submitted_at',
        'challan_no', 'challan_amount', 'challan_due_date', 'challan_issued_at', 'challan_paid_at',
        'challan_file_path', 'payment_receipt_path', 'payment_verified_at', 'payment_verified_by',
        'reactivated_at', 'reactivated_by', 'reactivation_reason',
        'allocated_quantity', 'distributed_quantity', 'distributed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'received_at' => 'datetime',
            'assigned_at' => 'datetime',
            'stage_updated_at' => 'datetime',
            'correction_submitted_at' => 'datetime',
            'challan_due_date' => 'date',
            'challan_issued_at' => 'datetime',
            'challan_paid_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'distributed_at' => 'datetime',
            'is_unassigned' => 'boolean',
            'challan_amount' => 'decimal:2',
            'lock_version' => 'integer',
        ];
    }

    public function statusLabel(): string
    {
        return self::translateStatus((string) ($this->current_stage ?: $this->status));
    }

    public static function translateStatus(string $status): string
    {
        return match ($status) {
            'pending', self::STAGE_SUBMITTED => 'Submitted / Received',
            self::STAGE_UNDER_REVIEW => 'DD Review',
            self::STAGE_CORRECTION_REQUIRED => 'Correction Required',
            self::STAGE_FIELD_SURVEY_ASSIGNED => 'Field Survey Assigned',
            self::STAGE_VISIT_SCHEDULED => 'Visit Scheduled',
            self::STAGE_VISIT_COMPLETED => 'Visit Completed',
            self::STAGE_FINDINGS_SUBMITTED => 'Survey Findings Submitted',
            'directorate_approved', self::STAGE_APPROVED => 'Approved (DD Decision)',
            self::STAGE_CHALLAN_ISSUED => 'Challan Issued',
            self::STAGE_PAYMENT_PROOF_SUBMITTED => 'Payment Proof Submitted',
            self::STAGE_PAYMENT_VERIFIED => 'Payment Verified (Accounts Clearance)',
            self::STAGE_ELIGIBLE_BENEFICIARY => 'Eligible Beneficiary',
            self::STAGE_STOCK_ALLOCATED => 'Stock Allocated',
            self::STAGE_PARTIALLY_DISTRIBUTED => 'Partially Distributed',
            'disbursed', self::STAGE_FULLY_DISTRIBUTED => 'Fully Distributed',
            self::STAGE_COMPLETED => 'Completed',
            self::STAGE_REJECTED => 'Rejected',
            self::STAGE_DORMANT => 'Dormant (Expired Challan)',
            'district_verified' => 'District Verified',
            default => ucfirst(str_replace('_', ' ', $status)),
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responsibleOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'responsible_office_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(ApplicationSurvey::class)->latest();
    }

    public function latestSurvey(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ApplicationSurvey::class)->latestOfMany();
    }

    public function paymentVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_verified_by');
    }

    public function reactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    /**
     * Stage Ageing in hours
     */
    public function stageAgeingHours(): int
    {
        $timestamp = $this->stage_updated_at ?? $this->updated_at ?? $this->created_at ?? now();
        return (int) max(0, $timestamp->diffInHours(now()));
    }

    /**
     * Stage Ageing in days
     */
    public function stageAgeingDays(): int
    {
        $timestamp = $this->stage_updated_at ?? $this->updated_at ?? $this->created_at ?? now();
        return (int) max(0, $timestamp->diffInDays(now()));
    }

    /**
     * Total processing time in days
     */
    public function totalAgeingDays(): int
    {
        $timestamp = $this->received_at ?? $this->submitted_at ?? $this->created_at ?? now();
        return (int) max(0, $timestamp->diffInDays(now()));
    }

    /**
     * SLA target hours configured per stage
     */
    public function slaTargetHours(): int
    {
        $stage = $this->current_stage ?: $this->status;
        return match ($stage) {
            self::STAGE_SUBMITTED => 48, // 2 days to acknowledge/assign
            self::STAGE_UNDER_REVIEW => 72, // 3 days for DD review
            self::STAGE_FIELD_SURVEY_ASSIGNED => 48, // 2 days to schedule visit
            self::STAGE_VISIT_SCHEDULED => 72, // 3 days to complete visit
            self::STAGE_VISIT_COMPLETED => 48, // 2 days to submit findings
            self::STAGE_FINDINGS_SUBMITTED => 48, // 2 days for DD final decision
            self::STAGE_CHALLAN_ISSUED => 336, // 14 calendar days (336 hours)
            self::STAGE_PAYMENT_PROOF_SUBMITTED => 48, // 2 days for Accounts Officer
            self::STAGE_PAYMENT_VERIFIED => 48, // 2 days to confirm eligibility
            self::STAGE_ELIGIBLE_BENEFICIARY => 72, // 3 days to allocate stock
            self::STAGE_STOCK_ALLOCATED => 120, // 5 days to disburse
            self::STAGE_PARTIALLY_DISTRIBUTED => 168, // 7 days to complete
            self::STAGE_CORRECTION_REQUIRED => 168, // 7 days citizen response
            default => 120,
        };
    }

    /**
     * Current SLA clock status: normal, warning (>=75%), breached (>100%)
     */
    public function slaStatus(): array
    {
        $target = $this->slaTargetHours();
        $elapsed = $this->stageAgeingHours();
        $percentage = $target > 0 ? round(($elapsed / $target) * 100) : 0;

        if (in_array($this->current_stage, [self::STAGE_COMPLETED, self::STAGE_FULLY_DISTRIBUTED, self::STAGE_REJECTED], true)) {
            return [
                'state' => 'completed',
                'badge' => 'success',
                'label' => 'Lifecycle Completed',
                'elapsed_hours' => $elapsed,
                'target_hours' => $target,
                'percentage' => 100,
            ];
        }

        if ($this->current_stage === self::STAGE_DORMANT) {
            return [
                'state' => 'dormant',
                'badge' => 'secondary',
                'label' => 'Dormant (Unpaid)',
                'elapsed_hours' => $elapsed,
                'target_hours' => $target,
                'percentage' => 100,
            ];
        }

        if ($elapsed > $target) {
            return [
                'state' => 'breached',
                'badge' => 'danger',
                'label' => 'SLA Breached (' . round($elapsed / 24, 1) . 'd / ' . round($target / 24, 1) . 'd)',
                'elapsed_hours' => $elapsed,
                'target_hours' => $target,
                'percentage' => $percentage,
            ];
        }

        if ($percentage >= 75) {
            return [
                'state' => 'warning',
                'badge' => 'warning',
                'label' => 'SLA Warning (' . round($elapsed / 24, 1) . 'd / ' . round($target / 24, 1) . 'd)',
                'elapsed_hours' => $elapsed,
                'target_hours' => $target,
                'percentage' => $percentage,
            ];
        }

        return [
            'state' => 'normal',
            'badge' => 'info',
            'label' => 'On Track (' . round($elapsed / 24, 1) . 'd / ' . round($target / 24, 1) . 'd)',
            'elapsed_hours' => $elapsed,
            'target_hours' => $target,
            'percentage' => $percentage,
        ];
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

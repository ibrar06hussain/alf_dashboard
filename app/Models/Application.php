<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
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

    public static function generateApplicationNo(): string
    {
        return 'ALF-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -6));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationSurvey extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_VISIT_SCHEDULED = 'visit_scheduled';
    public const STATUS_VISIT_COMPLETED = 'visit_completed';
    public const STATUS_FINDINGS_SUBMITTED = 'findings_submitted';
    public const STATUS_CANCELLED = 'cancelled';

    public const REC_RECOMMENDED = 'recommended';
    public const REC_NOT_RECOMMENDED = 'not_recommended';
    public const REC_NEEDS_FURTHER_AUDIT = 'needs_further_audit';

    protected $fillable = [
        'application_id',
        'assigned_to',
        'assigned_by',
        'status',
        'scheduled_visit_at',
        'visit_completed_at',
        'findings_submitted_at',
        'land_verified_kanals',
        'water_source_verified',
        'road_access_verified',
        'findings_summary',
        'recommendation',
        'evidence_data',
        'gps_coordinates',
        'gps_points',
        'photos',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_visit_at' => 'datetime',
            'visit_completed_at' => 'datetime',
            'findings_submitted_at' => 'datetime',
            'land_verified_kanals' => 'float',
            'evidence_data' => 'array',
            'gps_points' => 'array',
            'photos' => 'array',
        ];
    }

    public function getGpsPointsListAttribute(): array
    {
        if (! empty($this->gps_points) && is_array($this->gps_points)) {
            return $this->gps_points;
        }

        if (! empty($this->evidence_data['gps_points']) && is_array($this->evidence_data['gps_points'])) {
            return $this->evidence_data['gps_points'];
        }

        // Fallback: parse gps_coordinates string if format is "lat,lng" or JSON
        if (! empty($this->gps_coordinates)) {
            $decoded = json_decode($this->gps_coordinates, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            if (str_contains($this->gps_coordinates, ',')) {
                [$lat, $lng] = array_map('trim', explode(',', $this->gps_coordinates, 2));
                return [
                    ['point_number' => 1, 'lat' => (float) $lat, 'lng' => (float) $lng, 'label' => 'Point 1'],
                ];
            }
        }

        return [];
    }

    public function getPhotosListAttribute(): array
    {
        if (! empty($this->photos) && is_array($this->photos)) {
            return $this->photos;
        }

        if (! empty($this->evidence_data['photos']) && is_array($this->evidence_data['photos'])) {
            return $this->evidence_data['photos'];
        }

        return [];
    }

    public function getGoogleMapsUrlAttribute(): ?string
    {
        $points = $this->gps_points_list;
        if (empty($points)) {
            return null;
        }

        $first = $points[0];
        $lat = $first['lat'] ?? null;
        $lng = $first['lng'] ?? null;
        if ($lat && $lng) {
            return "https://www.google.com/maps?q={$lat},{$lng}";
        }

        return null;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_VISIT_COMPLETED, self::STATUS_FINDINGS_SUBMITTED], true);
    }
}

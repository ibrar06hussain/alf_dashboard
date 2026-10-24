<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAssignment extends Model
{
    public const TASK_LAND_VERIFICATION = 'land_verification';
    public const TASK_SEED_DISTRIBUTION = 'seed_distribution';
    public const TASK_TECHNICAL_AUDIT = 'technical_audit';

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'application_id',
        'assigned_to',
        'assigned_by',
        'task_type',
        'status',
        'due_date',
        'completed_at',
        'notes',
        'report_data',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'report_data' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getGpsPointsListAttribute(): array
    {
        if (! empty($this->report_data['gps_points']) && is_array($this->report_data['gps_points'])) {
            return $this->report_data['gps_points'];
        }

        return [];
    }

    public function getPhotosListAttribute(): array
    {
        if (! empty($this->report_data['photos']) && is_array($this->report_data['photos'])) {
            return $this->report_data['photos'];
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
}

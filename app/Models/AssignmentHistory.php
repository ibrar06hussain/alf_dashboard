<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentHistory extends Model
{
    protected $fillable = [
        'user_id',
        'assignment_id',
        'action',
        'old_state_json',
        'new_state_json',
        'reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_state_json' => 'array',
            'new_state_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}

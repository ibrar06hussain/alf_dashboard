<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationEditAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'application_no',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'action',
        'old_values',
        'new_values',
        'changed_fields',
        'modification_reason',
        'ip_address',
        'user_agent',
        'modified_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'modified_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


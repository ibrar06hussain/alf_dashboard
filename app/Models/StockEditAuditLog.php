<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEditAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_type',
        'stock_id',
        'reference_no',
        'user_id',
        'user_role',
        'old_values',
        'new_values',
        'changed_fields',
        'justification',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GbpayResponse extends Model
{
    use HasFactory;

    protected $table = 'gbpay_responses';
    protected $primaryKey = 'response_id';

    protected $fillable = [
        'bill_id',
        'psid',
        'request_type',
        'request_data',
        'response_data',
        'status_code',
        'status_message',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id', 'bill_id');
    }
}


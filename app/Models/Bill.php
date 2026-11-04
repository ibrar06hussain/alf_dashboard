<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bills';
    protected $primaryKey = 'bill_id';

    protected $fillable = [
        'application_id',
        'psid',
        'bill_amount',
        'status',
        'generated_at',
        'due_date',
        'paid_at',
        'gbpay_response',
        'transaction_id',
        'bank_mnemonic',
        'challan_data',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'gbpay_response' => 'array',
            'challan_data' => 'array',
        ];
    }

    /**
     * Always round bill_amount up to the nearest upper integer when setting.
     */
    public function setBillAmountAttribute($value): void
    {
        $this->attributes['bill_amount'] = $value !== null && $value !== ''
            ? (int) ceil((float) $value)
            : 0;
    }

    /**
     * Always return bill_amount as an integer rounded up to the nearest upper integer.
     */
    public function getBillAmountAttribute($value): int
    {
        return $value !== null && $value !== ''
            ? (int) ceil((float) $value)
            : 0;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function gbpayResponses(): HasMany
    {
        return $this->hasMany(GbpayResponse::class, 'bill_id', 'bill_id')->latest();
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}


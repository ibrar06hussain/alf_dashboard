<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_no',
        'scheme_stock_item_id',
        'from_center_id',
        'to_center_id',
        'quantity',
        'demand_justification',
        'status',
        'transferred_by',
        'received_by',
        'transfer_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    public function fromCenter(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'from_center_id');
    }

    public function toCenter(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'to_center_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

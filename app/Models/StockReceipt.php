<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no',
        'scheme_stock_item_id',
        'collection_center_id',
        'district_id',
        'quantity_received',
        'received_date',
        'supplier_source',
        'batch_no',
        'received_by',
        'proof_attachment_path',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'received_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'collection_center_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

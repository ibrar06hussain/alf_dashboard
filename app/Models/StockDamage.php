<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDamage extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_no',
        'collection_center_id',
        'scheme_stock_item_id',
        'quantity',
        'damage_type',
        'reason',
        'proof_file_path',
        'recorded_by',
        'action_taken',
        'incident_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'incident_date' => 'date',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'collection_center_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_center_id',
        'scheme_stock_item_id',
        'quantity_received',
        'quantity_transferred_in',
        'quantity_transferred_out',
        'quantity_distributed',
        'quantity_damaged',
        'current_balance',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'quantity_transferred_in' => 'decimal:2',
        'quantity_transferred_out' => 'decimal:2',
        'quantity_distributed' => 'decimal:2',
        'quantity_damaged' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'collection_center_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    /**
     * Recalculate balance based on actual ledger metrics.
     */
    public function recalculate(): self
    {
        $this->current_balance = (
            (float) $this->quantity_received +
            (float) $this->quantity_transferred_in -
            (float) $this->quantity_transferred_out -
            (float) $this->quantity_distributed -
            (float) $this->quantity_damaged
        );

        $this->save();

        return $this;
    }
}

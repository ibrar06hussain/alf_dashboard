<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchemeStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheme_id',
        'item_name',
        'item_code',
        'unit',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(CenterInventory::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(StockDamage::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(StockDisbursement::class);
    }
}

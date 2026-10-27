<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'level',
        'district_id',
        'tehsil_id',
        'village_name',
        'focal_person_name',
        'focal_person_phone',
        'address',
        'capacity_units',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity_units' => 'decimal:2',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(CenterInventory::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_center_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_center_id');
    }

    public function damages(): HasMany
    {
        return $this->hasMany(StockDamage::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(StockDisbursement::class);
    }

    /**
     * Scope query to the user's authorized district if the user is district-scoped.
     */
    public function scopeScopedToUser(Builder $query, ?User $user = null): Builder
    {
        if (! $user) {
            $user = auth()->user();
        }

        if ($user && ! $user->hasAnyRole(['Super Admin', 'Secretary', 'Director Agriculture', 'Director Livestock', 'Director Fisheries'])) {
            $districts = $user->authorizedDistrictIds();
            if (! empty($districts)) {
                $query->whereIn('district_id', $districts);
            }
        }

        return $query;
    }

    /**
     * Get or create inventory record for a specific scheme stock item.
     */
    public function getInventoryForItem(int $schemeStockItemId): CenterInventory
    {
        return $this->inventories()->firstOrCreate(
            ['scheme_stock_item_id' => $schemeStockItemId],
            [
                'quantity_received' => 0,
                'quantity_transferred_in' => 0,
                'quantity_transferred_out' => 0,
                'quantity_distributed' => 0,
                'quantity_damaged' => 0,
                'current_balance' => 0,
            ]
        );
    }
}

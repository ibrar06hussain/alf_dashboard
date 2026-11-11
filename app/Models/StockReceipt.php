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

    public function editLogs()
    {
        return $this->hasMany(StockEditAuditLog::class, 'stock_id')
            ->where('stock_type', 'receipt')
            ->latest();
    }

    /**
     * Check if the given user can edit this stock receipt.
     * Rule: Super Admin and scoped Distribution Officers can edit anytime (with mandatory audit justification).
     * Other authorized officers/creators can edit within 10 minutes of creation.
     */
    public function canUserEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($user->hasRole('Distribution Officer')) {
            if (! $user->isGlobalScope()) {
                $authorizedDistricts = $user->authorizedDistrictIds();
                if (! empty($authorizedDistricts) && ! in_array((int) $this->district_id, $authorizedDistricts, true)) {
                    return false;
                }
            }

            return true;
        }

        // Within 10 minutes of creation for other recording staff/officers
        $isWithinWindow = $this->created_at && $this->created_at->diffInMinutes(now()) <= 10;
        if (! $isWithinWindow) {
            return false;
        }

        // Must be the user who recorded it or an authorized officer
        return (int) $this->received_by === (int) $user->id || $user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries', 'District Deputy Director', 'District Officer']);
    }

    /**
     * Minutes remaining in the initial 10-minute edit window.
     */
    public function editMinutesRemaining(): int
    {
        if (! $this->created_at) {
            return 0;
        }
        $diff = 10 - $this->created_at->diffInMinutes(now());
        return max(0, (int) $diff);
    }
}

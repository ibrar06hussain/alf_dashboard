<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDisbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'disbursement_no',
        'application_id',
        'collection_center_id',
        'scheme_stock_item_id',
        'district_id',
        'quantity_disbursed',
        'recipient_name',
        'recipient_cnic',
        'recipient_phone',
        'is_original_applicant',
        'representative_relationship',
        'challan_no',
        'challan_verified',
        'disbursement_date',
        'disbursed_by',
        'proof_attachment_path',
        'notes',
    ];

    protected $casts = [
        'quantity_disbursed' => 'decimal:2',
        'disbursement_date' => 'date',
        'is_original_applicant' => 'boolean',
        'challan_verified' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(CollectionCenter::class, 'collection_center_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function editLogs()
    {
        return $this->hasMany(StockEditAuditLog::class, 'stock_id')
            ->where('stock_type', 'disbursement')
            ->latest();
    }

    /**
     * Check if the given user can edit this stock disbursement.
     * Rule: Within 10 minutes of creation, creator or admin can edit.
     * After 10 minutes, ONLY Super Admin can edit.
     */
    public function canUserEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Within 10 minutes of creation
        $isWithinWindow = $this->created_at && $this->created_at->diffInMinutes(now()) <= 10;
        if (! $isWithinWindow) {
            return false;
        }

        // Must be the user who disbursed it or an authorized officer
        return (int) $this->disbursed_by === (int) $user->id || $user->hasAnyRole(['Director Agriculture', 'Director Livestock', 'Director Fisheries', 'District Deputy Director', 'District Officer', 'Distribution Officer']);
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

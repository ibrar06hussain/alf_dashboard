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
}

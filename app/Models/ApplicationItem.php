<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApplicationItem extends Model
{
    protected $fillable = [
        'application_id',
        'form_field_id',
        'item_key',
        'item_label',
        'item_label_ur',
        'unit_label',
        'is_free',
        'unit_price',
        'govt_share_pct',
        'inherits_template_share',
        'farmer_share_pct',
        'demanded_qty',
        'approved_qty',
        'disbursed_qty',
        'gross_amount',
        'govt_share_amount',
        'farmer_payable_amount',
        'scheme_stock_item_id',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'inherits_template_share' => 'boolean',
            'unit_price' => 'decimal:2',
            'govt_share_pct' => 'decimal:2',
            'farmer_share_pct' => 'decimal:2',
            'demanded_qty' => 'decimal:2',
            'approved_qty' => 'decimal:2',
            'disbursed_qty' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'govt_share_amount' => 'decimal:2',
            'farmer_payable_amount' => 'decimal:2',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(SchemeStockItem::class, 'scheme_stock_item_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(StockDisbursement::class, 'application_item_id');
    }

    /**
     * Effective quantity used for billing/disbursement (approved if set, else demanded).
     */
    public function effectiveQty(): float
    {
        return (float) ($this->approved_qty !== null ? $this->approved_qty : $this->demanded_qty);
    }

    public function remainingDisbursementQty(): float
    {
        return max(0.0, round($this->effectiveQty() - (float) $this->disbursed_qty, 2));
    }

    /**
     * Resolve effective Govt/Farmer share percentages, dynamically inheriting
     * from the Scheme's ChallanTemplate when Form Builder left Govt Share blank.
     *
     * @return array{govt_share_pct: float, farmer_share_pct: float}
     */
    public function effectiveSharePercentages(?ChallanTemplate $template = null, bool $isPaidFrozen = false): array
    {
        if ($this->is_free || (float) $this->unit_price <= 0) {
            return [
                'govt_share_pct' => 100.0,
                'farmer_share_pct' => 0.0,
            ];
        }

        if ($this->inherits_template_share && ! $isPaidFrozen && $template) {
            if (($template->fee_mode ?? null) === 'free') {
                return [
                    'govt_share_pct' => 100.0,
                    'farmer_share_pct' => 0.0,
                ];
            }

            $govtPct = max(0.0, min(100.0, (float) ($template->govt_share_pct ?? 0)));
            $tplFarmer = $template->farmer_share_pct !== null
                ? max(0.0, min(100.0, (float) $template->farmer_share_pct))
                : null;
            $farmerPct = ($tplFarmer !== null && abs(($govtPct + $tplFarmer) - 100.0) <= 0.05)
                ? $tplFarmer
                : max(0.0, min(100.0, round(100.0 - $govtPct, 2)));

            return [
                'govt_share_pct' => $govtPct,
                'farmer_share_pct' => $farmerPct,
            ];
        }

        $govtPct = max(0.0, min(100.0, (float) $this->govt_share_pct));
        $farmerPct = max(0.0, min(100.0, round(100.0 - $govtPct, 2)));

        return [
            'govt_share_pct' => $govtPct,
            'farmer_share_pct' => $farmerPct,
        ];
    }

    /**
     * Recalculate gross, govt share, and farmer payable amounts from current unit pricing & effective qty.
     */
    public function recalculateFinancials(?ChallanTemplate $template = null, bool $isPaidFrozen = false): self
    {
        $qty = max(0.0, $this->effectiveQty());
        $unitPrice = (float) $this->unit_price;

        if ($this->is_free || $unitPrice <= 0) {
            $this->govt_share_pct = 100.0;
            $this->farmer_share_pct = 0.0;
            $this->gross_amount = 0;
            $this->govt_share_amount = 0;
            $this->farmer_payable_amount = 0;

            return $this;
        }

        $shares = $this->effectiveSharePercentages($template, $isPaidFrozen);
        $govtPct = $shares['govt_share_pct'];
        $farmerPct = $shares['farmer_share_pct'];

        $this->govt_share_pct = $govtPct;
        $this->farmer_share_pct = $farmerPct;

        if ($qty <= 0) {
            $this->gross_amount = 0;
            $this->govt_share_amount = 0;
            $this->farmer_payable_amount = 0;

            return $this;
        }

        $gross = (float) ceil($unitPrice * $qty);
        $govtAmount = (float) ceil($gross * ($govtPct / 100));
        $farmerAmount = (float) ceil($gross * ($farmerPct / 100));

        $this->gross_amount = $gross;
        $this->govt_share_amount = $govtAmount;
        $this->farmer_payable_amount = $farmerAmount;

        return $this;
    }

    public static function normalizeKey(string $value): string
    {
        $slug = Str::slug(trim($value), '_');

        return $slug !== '' ? $slug : md5(strtolower(trim($value)));
    }
}

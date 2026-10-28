<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChallanTemplate extends Model
{
    protected $fillable = [
        'name',
        'department_name',
        'sub_title',
        'challan_title',
        'bank_name',
        'account_title',
        'account_no',
        'account_head',
        'rco_code',
        'head_code',
        'validity_days',
        'directions',
        'fee_mode',
        'govt_share_pct',
        'farmer_share_pct',
        'show_breakdown',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'validity_days' => 'integer',
            'govt_share_pct' => 'decimal:2',
            'farmer_share_pct' => 'decimal:2',
            'show_breakdown' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function schemes(): HasMany
    {
        return $this->hasMany(Scheme::class, 'challan_template_id');
    }

    /**
     * Get active default template or fallback object.
     */
    public static function getDefault(): self
    {
        $template = static::where('is_default', true)->where('is_active', true)->first();
        if ($template) {
            return $template;
        }

        $template = static::where('is_active', true)->first();
        if ($template) {
            return $template;
        }

        return new static([
            'name' => 'Default Department Challan',
            'department_name' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'sub_title' => 'Government of Gilgit-Baltistan',
            'challan_title' => 'FEE DEPOSIT CHALLAN',
            'bank_name' => 'National Bank Of Pakistan',
            'account_title' => 'Agriculture LiveStock and Fishries Department Gilgit Baltistan',
            'account_no' => '4256260305',
            'account_head' => 'Application Processing Fee(C03870)',
            'rco_code' => 'GL1800',
            'head_code' => 'C03870',
            'validity_days' => 10,
            'directions' => 'This challan is valid for 10 days after the challan is generated. This fee doesnot include transport charges from district to village.',
            'fee_mode' => 'seed_configured',
            'govt_share_pct' => 50.00,
            'farmer_share_pct' => 50.00,
            'show_breakdown' => true,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}

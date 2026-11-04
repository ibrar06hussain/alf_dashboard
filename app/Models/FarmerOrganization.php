<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerOrganization extends Model
{
    use HasFactory;

    public const SHEET_DISTRICT_MAP = [
        'GLT' => ['district' => 'Gilgit', 'division' => 'Gilgit'],
        'HNZ' => ['district' => 'Hunza', 'division' => 'Gilgit'],
        'NGR' => ['district' => 'Nagar', 'division' => 'Gilgit'],
        'GZR' => ['district' => 'Ghizer', 'division' => 'Gilgit'],
        'Diamer' => ['district' => 'Diamer', 'division' => 'Diamer-Astore'],
        'AST' => ['district' => 'Astore', 'division' => 'Diamer-Astore'],
        'SKD' => ['district' => 'Skardu', 'division' => 'Baltistan'],
        'GHE' => ['district' => 'Ghanche', 'division' => 'Baltistan'],
        'KMG' => ['district' => 'Kharmang', 'division' => 'Baltistan'],
        'SGR' => ['district' => 'Shigar', 'division' => 'Baltistan'],
    ];

    protected $fillable = [
        'district_id',
        'sheet_code',
        'division_name',
        'district_name',
        'sub_division',
        'uc_mc',
        'village_name',
        'organization_name',
        'is_vo_formed',
        'focal_person_name',
        'focal_person_phone',
        'focal_person_raw',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_vo_formed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Resolve sheet code from district name.
     */
    public static function resolveSheetCode(string $districtName): string
    {
        foreach (self::SHEET_DISTRICT_MAP as $code => $meta) {
            if (strcasecmp($meta['district'], trim($districtName)) === 0) {
                return $code;
            }
        }
        return 'GLT';
    }

    /**
     * Resolve division name from district name.
     */
    public static function resolveDivisionName(string $districtName): string
    {
        foreach (self::SHEET_DISTRICT_MAP as $meta) {
            if (strcasecmp($meta['district'], trim($districtName)) === 0) {
                return $meta['division'];
            }
        }
        return 'Gilgit';
    }
}


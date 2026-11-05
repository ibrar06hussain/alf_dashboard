<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsidConfig extends Model
{
    use HasFactory;

    protected $table = 'psid_config';
    protected $primaryKey = 'config_id';

    protected $fillable = [
        'one_link_prefix',
        'department_code',
        'district_code',
        'department_service_no',
        'rco',
        'head',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const DISTRICT_CODES = [
        '01' => ['name' => 'Gilgit', 'alpha' => 'GL'],
        '02' => ['name' => 'Nagar', 'alpha' => 'NG'],
        '03' => ['name' => 'Hunza', 'alpha' => 'HN'],
        '04' => ['name' => 'Ghizer', 'alpha' => 'GZ'],
        '05' => ['name' => 'Astore', 'alpha' => 'AT'],
        '06' => ['name' => 'Diamer', 'alpha' => 'DM'],
        '07' => ['name' => 'Skardu', 'alpha' => 'SD'],
        '08' => ['name' => 'Kharmang / Roundu', 'alpha' => 'RG'],
        '09' => ['name' => 'Shigar', 'alpha' => 'SS'],
        '10' => ['name' => 'Ghanche', 'alpha' => 'GN'],
    ];

    /**
     * Alias for getActive().
     */
    public static function active(): self
    {
        return self::getActive();
    }

    /**
     * Get the active PSID configuration (or fallback default instance).
     */
    public static function getActive(): self
    {
        $config = static::where('is_active', true)->first() ?? static::first();

        if ($config) {
            return $config;
        }

        return new static([
            'one_link_prefix' => '101530',
            'department_code' => '09',
            'district_code' => '01',
            'department_service_no' => '01',
            'rco' => 'GL1618',
            'head' => 'C03870',
            'is_active' => true,
        ]);
    }

    /**
     * Generate 20-digit PSID with unique 10-digit voucher number.
     * Format: 1Link Prefix (6) + Dept Code (2) + Service Code (2) + Voucher Number (10) = 20 digits
     */
    public function generatePsid(?string $districtCodeOverride = null): string
    {
        $prefix = str_pad(substr((string) ($this->one_link_prefix ?: '101530'), 0, 6), 6, '0', STR_PAD_LEFT);
        $deptCode = str_pad(substr((string) ($this->department_code ?: '09'), 0, 2), 2, '0', STR_PAD_LEFT);
        $serviceNo = str_pad(substr((string) ($this->department_service_no ?: '01'), 0, 2), 2, '0', STR_PAD_LEFT);
        $uniqueVoucherId = str_pad((string) random_int(1, 9999999999), 10, '0', STR_PAD_LEFT);

        return $prefix . $deptCode . $serviceNo . $uniqueVoucherId;
    }

    /**
     * Resolve 2-digit GBPay district code from District model or name.
     */
    public static function resolveDistrictDigits(?District $district = null): ?string
    {
        if (!$district) {
            return null;
        }

        $name = strtolower(trim((string) $district->name));
        $code = strtoupper(trim((string) $district->code));

        $mapByName = [
            'gilgit' => '01',
            'nagar' => '02',
            'hunza' => '03',
            'ghizer' => '04',
            'astore' => '05',
            'diamer' => '06',
            'skardu' => '07',
            'kharmang' => '08',
            'roundu' => '08',
            'shigar' => '09',
            'ghanche' => '10',
        ];

        foreach ($mapByName as $key => $digits) {
            if (str_contains($name, $key)) {
                return $digits;
            }
        }

        $mapByCode = [
            'GL' => '01',
            'GLT' => '01',
            'NG' => '02',
            'NGR' => '02',
            'HN' => '03',
            'HNZ' => '03',
            'GZ' => '04',
            'GHZ' => '04',
            'AT' => '05',
            'AST' => '05',
            'DM' => '06',
            'DMR' => '06',
            'SD' => '07',
            'SKD' => '07',
            'RG' => '08',
            'KMG' => '08',
            'KH' => '08',
            'SS' => '09',
            'SHG' => '09',
            'GN' => '10',
            'GHC' => '10',
        ];

        return $mapByCode[$code] ?? null;
    }

    /**
     * Get 2-letter GBPay district abbreviation from 2-digit code or District model.
     */
    public static function getDistrictAbbreviation(?string $code = null, ?District $district = null): string
    {
        if ($district) {
            $digits = self::resolveDistrictDigits($district);
            if ($digits) {
                $code = $digits;
            }
        }

        $districts = [
            '01' => 'GL', // Gilgit
            '02' => 'NG', // Nagar
            '03' => 'HN', // Hunza
            '04' => 'GZ', // Ghizer
            '05' => 'AT', // Astore
            '06' => 'DM', // Diamer
            '07' => 'SD', // Skardu
            '08' => 'RG', // Kharmang
            '09' => 'SS', // Shigar
            '10' => 'GN', // Ghanche
        ];

        return $districts[$code ?? '01'] ?? 'GL';
    }

    /**
     * Map of District codes/names to Government of GB Agriculture RCO codes.
     */
    public const DISTRICT_RCO_CODES = [
        'GL' => 'GL1618', // Deputy Director Agriculture Gilgit
        'GZ' => 'GZ1050', // Deputy Director Agriculture Ghizer
        'HN' => 'HN1006', // Deputy Director Agriculture Hunza
        'NG' => 'NG1002', // Deputy Director Agriculture Nagar
        'DM' => 'DM1035', // Deputy Director Agriculture Diamer
        'AT' => 'AT1042', // Deputy Director Agriculture Astore
        'SD' => 'SD1066', // Deputy Director Agriculture Skardu
        'SS' => 'SS1008', // Deputy Director Agriculture Shigar
        'RG' => 'RG1002', // Deputy Director Agriculture Kharmang
        'GN' => 'GN1042', // Deputy Director Agriculture Ghanche
    ];

    /**
     * Regional / Directorate RCO codes.
     */
    public const REGIONAL_RCO_CODES = [
        'gilgit_region' => 'GL1617', // Director Agriculture (Gilgit Region)
        'baltistan_region' => 'SD1811', // Director Agriculture Baltistan Region
        'diamer_astore_region' => 'DM1015', // Director Agriculture Diamer-Astore Region
    ];

    /**
     * Resolve district RCO code for GBPay and challan_data.
     */
    public static function resolveDistrictRco(?District $district = null, ?string $fallback = null): string
    {
        if ($district) {
            $code = strtoupper(trim((string) $district->code));
            if (isset(self::DISTRICT_RCO_CODES[$code])) {
                return self::DISTRICT_RCO_CODES[$code];
            }

            $name = strtolower(trim((string) $district->name));
            $byName = [
                'gilgit' => 'GL1618',
                'ghizer' => 'GZ1050',
                'hunza' => 'HN1006',
                'nagar' => 'NG1002',
                'diamer' => 'DM1035',
                'astore' => 'AT1042',
                'skardu' => 'SD1066',
                'shigar' => 'SS1008',
                'kharmang' => 'RG1002',
                'roundu' => 'RG1002',
                'ghanche' => 'GN1042',
            ];
            foreach ($byName as $k => $rco) {
                if (str_contains($name, $k)) {
                    return $rco;
                }
            }
        }

        return $fallback ?: 'GL1618';
    }
}

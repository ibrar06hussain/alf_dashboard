<?php

namespace App\Support;

use App\Models\FarmerOrganization;

class GbGeoCoordinates
{
    /**
     * Level 1: District Coordinates in Gilgit-Baltistan [lat, lng]
     */
    public const DISTRICTS = [
        'Gilgit'   => [35.9208, 74.3144],
        'Hunza'    => [36.3167, 74.6500],
        'Nagar'    => [36.2575, 74.5975],
        'Ghizer'   => [36.1764, 73.7639],
        'Diamer'   => [35.4206, 74.0967],
        'Astore'   => [35.3667, 74.8500],
        'Skardu'   => [35.2971, 75.6333],
        'Ghanche'  => [35.1583, 76.3319],
        'Kharmang' => [34.9469, 76.0219],
        'Shigar'   => [35.4247, 75.7389],
    ];

    /**
     * Normalize Sub-Division (Tehsil) names from Excel typos/variations
     */
    public static function normalizeSubDivision(string $raw, string $district): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $raw));
        if ($clean === '') {
            return $district;
        }
        $lower = strtolower($clean);

        return match ($lower) {
            'giglit', 'gilgit' => 'Gilgit',
            'danyore', 'danyor' => 'Danyore',
            'juglote', 'jaglote', 'jaglot' => 'Juglote',
            'sai bala', 'saibala' => 'Sai Bala',
            'sai pine', 'saipine', 'sai pain' => 'Sai Pine',
            'aliabad', 'ali abad' => 'Aliabad',
            'gojal', 'gujal' => 'Gojal',
            'nagar-1', 'nagar 1', 'nagar i' => 'Nagar-I',
            'nagar-2', 'nagar 2', 'nagar ii' => 'Nagar-II',
            'puniyal -iskoman', 'punial-ishkoman', 'puniyal', 'ishkoman' => 'Puniyal-Ishkoman',
            'gupis', 'gopis' => 'Gupis',
            'yasin' => 'Yasin',
            'chilas' => 'Chilas',
            'tangir' => 'Tangir',
            'darel' => 'Darel',
            'eidgah', 'eid gah', 'astore' => 'Eidgah',
            'shountar', 'shounter' => 'Shountar',
            'skardu' => 'Skardu',
            'gultari', 'gultri' => 'Gultari',
            'rundu', 'roundu' => 'Roundu',
            'khaplu' => 'Khaplu',
            'chorbat' => 'Chorbat',
            'mashabrum', 'masherbrum' => 'Mashabrum',
            'daghoni', 'doghani' => 'Daghoni',
            'kharmang', 'kharmung' => 'Kharmang',
            'shigar' => 'Shigar',
            default => ucwords($clean),
        };
    }

    /**
     * Normalize Union Council / MC names from Excel variations
     */
    public static function normalizeUc(string $raw, string $subDivision): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $raw));
        if ($clean === '') {
            return $subDivision;
        }
        $lower = strtolower($clean);

        return match ($lower) {
            'town area' => 'Gilgit Town Area',
            'minawar' => 'Minawar',
            'sakwr', 'sakwar' => 'Sakwar',
            'sakarkoi' => 'Sakarkoi',
            'sai bala' => 'Sai Bala',
            'sai pine' => 'Sai Pine',
            'jalalabd/oshkikhandas' => 'Jalalabad / Oshikhandass',
            'napora /baseen' => 'Napura / Basin',
            'ub nagar khas', 'uc nagar khas' => 'UC Nagar Khas',
            'uc sas valley' => 'UC SAS Valley (Hispar)',
            'mc' => 'MC Gahkuch',
            'gpis', 'gupis' => 'Gupis',
            'jaglote' => 'Jaglote (Tangir)',
            'botogah/khiner' => 'Butogah / Khiner',
            'uc gultri', 'uc gultari' => 'UC Gultari',
            'uc shingo shigar' => 'UC Shingo Shigar',
            'uc shigariKhord', 'uc shigarikhord' => 'UC Shigari Khurd',
            'bilamik', 'uc bilamik' => 'UC Bilamik',
            'roundu' => 'UC Roundu (Dambudas)',
            'ghowari' => 'Ghowari',
            'daghoni' => 'Daghoni',
            'khaplu' => 'Khaplu',
            'machulu', 'machulo' => 'Machulo',
            'thagus', 'thaghus' => 'Thagas',
            'oding', 'olding' => 'Olding',
            default => $clean,
        };
    }

    /**
     * Level 2: Sub-Division / Tehsil Coordinates [lat, lng]
     * Keyed by "District|SubDivision"
     */
    public const TEHSILS = [
        // Gilgit District
        'Gilgit|Gilgit'             => [35.9208, 74.3144],
        'Gilgit|Danyore'            => [35.9256, 74.3936],
        'Gilgit|Juglote'            => [35.6881, 74.6253],
        'Gilgit|Sai Bala'           => [35.7250, 74.5450],
        'Gilgit|Sai Pine'           => [35.7020, 74.5920],

        // Hunza District
        'Hunza|Aliabad'             => [36.3078, 74.6158],
        'Hunza|Gojal'               => [36.5019, 74.8711],

        // Nagar District
        'Nagar|Nagar-I'             => [36.2575, 74.7250],
        'Nagar|Nagar-II'            => [36.2450, 74.4250],

        // Ghizer District
        'Ghizer|Puniyal-Ishkoman'   => [36.1764, 73.7639],
        'Ghizer|Gupis'              => [36.2289, 73.4414],
        'Ghizer|Yasin'              => [36.3772, 73.3364],

        // Diamer District
        'Diamer|Chilas'             => [35.4206, 74.0967],
        'Diamer|Tangir'             => [35.4850, 73.4250],
        'Diamer|Darel'              => [35.5650, 73.5950],

        // Astore District
        'Astore|Eidgah'             => [35.3667, 74.8500],
        'Astore|Shountar'           => [35.1450, 74.9850],

        // Skardu District
        'Skardu|Skardu'             => [35.2971, 75.6333],
        'Skardu|Gultari'            => [34.8150, 75.4850],
        'Skardu|Roundu'             => [35.5719, 75.1583],

        // Ghanche District
        'Ghanche|Khaplu'            => [35.1583, 76.3319],
        'Ghanche|Chorbat'           => [35.0250, 76.6850],
        'Ghanche|Mashabrum'         => [35.1950, 76.4850],
        'Ghanche|Daghoni'           => [35.2250, 76.1450],

        // Kharmang District
        'Kharmang|Kharmang'         => [34.9469, 76.0219],

        // Shigar District
        'Shigar|Shigar'             => [35.4247, 75.7389],
    ];

    /**
     * Level 3: Union Council / MC Coordinates [lat, lng]
     * Keyed by "District|NormalizedUC"
     */
    public const UNION_COUNCILS = [
        // --- GILGIT DISTRICT ---
        'Gilgit|Danyore'                        => [35.9256, 74.3936],
        'Gilgit|Rahimabad'                      => [35.9850, 74.3650],
        'Gilgit|Jalalabad / Oshikhandass'       => [35.8850, 74.4620],
        'Gilgit|Bagrote'                        => [36.0210, 74.5520],
        'Gilgit|Haramosh'                       => [35.7850, 74.7950],
        'Gilgit|Nomal'                          => [36.0825, 74.2830],
        'Gilgit|Naltar'                         => [36.1610, 74.1850],
        'Gilgit|Bargo Sharote'                  => [35.9520, 74.2050],
        'Gilgit|Napura / Basin'                 => [35.9150, 74.2620],
        'Gilgit|Gilgit Town Area'               => [35.9208, 74.3144],
        'Gilgit|Minawar'                        => [35.8620, 74.4150],
        'Gilgit|Pari'                           => [35.7880, 74.5420],
        'Gilgit|Sakwar'                         => [35.8880, 74.3750],
        'Gilgit|Sakarkoi'                       => [35.9050, 74.3420],
        'Gilgit|Juglote'                        => [35.6881, 74.6253],
        'Gilgit|Sai Bala'                       => [35.7250, 74.5450],
        'Gilgit|Sai Pine'                       => [35.7020, 74.5920],

        // --- HUNZA DISTRICT ---
        'Hunza|Shinaki'                         => [36.2650, 74.5250],
        'Hunza|Aliabad'                         => [36.3078, 74.6158],
        'Hunza|Hyderabad'                       => [36.3180, 74.6420],
        'Hunza|Karimabad'                       => [36.3269, 74.6642],
        'Hunza|Ganish'                          => [36.3150, 74.6750],
        'Hunza|Altit'                           => [36.3320, 74.6850],
        'Hunza|Gojal-I'                         => [36.3850, 74.8650], // Gulmit / Shishkat
        'Hunza|Gojal-II'                        => [36.6850, 74.8250], // Passu / Sost / Shimshal

        // --- NAGAR DISTRICT ---
        'Nagar|Hoper'                           => [36.2250, 74.7650],
        'Nagar|UC Nagar Khas'                   => [36.2650, 74.7150],
        'Nagar|UC SAS Valley (Hispar)'          => [36.1750, 74.9150],
        'Nagar|Phakar'                          => [36.2850, 74.5850],
        'Nagar|Gulmet'                          => [36.2450, 74.4950],
        'Nagar|Nilt'                            => [36.2420, 74.4220],
        'Nagar|Sikandarabad'                    => [36.2520, 74.3650],
        'Nagar|Chalt'                           => [36.2560, 74.3180],

        // --- GHIZER DISTRICT ---
        'Ghizer|Sherqilla'                      => [36.0650, 73.9450],
        'Ghizer|Singul'                         => [36.1150, 73.8650],
        'Ghizer|Buber'                          => [36.1420, 73.8150],
        'Ghizer|Hatoon'                         => [36.1850, 73.7950],
        'Ghizer|MC Gahkuch'                     => [36.1764, 73.7639],
        'Ghizer|Chatorkhand'                    => [36.3350, 73.8450],
        'Ghizer|Immit'                          => [36.5050, 73.8950],
        'Ghizer|Sumal'                          => [36.2050, 73.5650],
        'Ghizer|Gupis'                          => [36.2289, 73.4414],
        'Ghizer|Pingal'                         => [36.1950, 73.2450],
        'Ghizer|Phander'                        => [36.1720, 72.9450],
        'Ghizer|Teru'                           => [36.1450, 72.7650],
        'Ghizer|Yasin'                          => [36.3772, 73.3364],
        'Ghizer|Thous'                          => [36.4350, 73.3450],
        'Ghizer|Hundure'                        => [36.4850, 73.3150],
        'Ghizer|Thoi'                           => [36.5350, 73.4450],

        // --- DIAMER DISTRICT ---
        'Diamer|Gonerfarm'                      => [35.5650, 74.4250],
        'Diamer|Goharabad'                      => [35.4850, 74.3150],
        'Diamer|Butogah / Khiner'               => [35.3950, 74.1450],
        'Diamer|Thore / Hudur'                  => [35.4350, 73.9650],
        'Diamer|Thore/Hudur'                    => [35.4350, 73.9650],
        'Diamer|Thak Niat'                      => [35.2950, 74.1650],
        'Diamer|Jaglote (Tangir)'               => [35.4250, 73.4950],
        'Diamer|Tangir Gabbar'                  => [35.5350, 73.3850],
        'Diamer|Samigal'                        => [35.5250, 73.5950],
        'Diamer|Manikal'                        => [35.5850, 73.6250],
        'Diamer|Dodoshal Khanbary'              => [35.6350, 73.6550],

        // --- ASTORE DISTRICT ---
        'Astore|Bunji'                          => [35.6450, 74.6350],
        'Astore|Doyan'                          => [35.5150, 74.7150],
        'Astore|Eidgah'                         => [35.3667, 74.8500],
        'Astore|Gudai'                          => [35.1950, 74.8650],
        'Astore|Rehmanpur'                      => [35.3150, 74.8850],
        'Astore|Rattu'                          => [35.1450, 74.8050],
        'Astore|Marmai'                         => [35.0650, 74.9450],
        'Astore|Minimargh'                      => [34.7950, 75.0850],

        // --- SKARDU DISTRICT ---
        'Skardu|MC Skardu'                      => [35.2971, 75.6333],
        'Skardu|UC Sermik/Gole'                 => [35.2450, 75.8650],
        'Skardu|UC Hussainabad'                 => [35.3550, 75.5650],
        'Skardu|UC Shingo Shigar'               => [34.7650, 75.3450],
        'Skardu|UC Gultari'                     => [34.8150, 75.4850],
        'Skardu|UC Gamba'                       => [35.2750, 75.6950],
        'Skardu|UC Hoto/Ranga'                  => [35.3150, 75.5850],
        'Skardu|UC Shigari Kalan'               => [35.2550, 75.6150],
        'Skardu|UC Shigari Bala'                => [35.2350, 75.6050],
        'Skardu|UC Chunda'                      => [35.2450, 75.5750],
        'Skardu|UC Kachura'                     => [35.4250, 75.4450],
        'Skardu|UC Basho'                       => [35.4650, 75.3450],
        'Skardu|UC Kowardu'                     => [35.3450, 75.6350],
        'Skardu|UC Qumarah'                     => [35.3650, 75.5150],
        'Skardu|UC Shigari Khurd'               => [35.2680, 75.6250],
        'Skardu|UC Ganji'                       => [35.6150, 74.9850],
        'Skardu|UC Talu'                        => [35.5950, 75.0650],
        'Skardu|UC Thorsay/Harpo'               => [35.5550, 75.1150],
        'Skardu|UC Mendi'                       => [35.5650, 75.1850],
        'Skardu|UC Bilamik'                     => [35.5150, 75.2150],
        'Skardu|UC Tormik'                      => [35.6250, 75.2650],
        'Skardu|UC Bagardo/Bagicha'             => [35.5350, 75.2850],
        'Skardu|UC Stak'                        => [35.5850, 75.1350],
        'Skardu|UC Thowar'                      => [35.5950, 75.1950],
        'Skardu|UC Roundu (Dambudas)'           => [35.5719, 75.1583],

        // --- GHANCHE DISTRICT ---
        'Ghanche|Keris'                         => [35.2350, 75.9850],
        'Ghanche|Ghowari'                       => [35.2150, 76.0650],
        'Ghanche|Daghoni'                       => [35.2250, 76.1450],
        'Ghanche|Khaplu'                        => [35.1583, 76.3319],
        'Ghanche|Siksa'                         => [35.0450, 76.6250],
        'Ghanche|Frano'                         => [34.9950, 76.7450],
        'Ghanche|Dumsum'                        => [35.1650, 76.5450],
        'Ghanche|Machulo'                       => [35.1950, 76.4350],
        'Ghanche|Thagas'                        => [35.2150, 76.5150],
        'Ghanche|Surmo'                         => [35.1350, 76.3750],
        'Ghanche|Saltoro'                       => [35.2550, 76.6150],
        'Ghanche|Thally'                        => [35.3150, 76.1850],
        'Ghanche|Kharko'                        => [35.1850, 76.2250],

        // --- KHARMANG DISTRICT ---
        'Kharmang|Mehdiabad'                    => [35.0650, 75.8950],
        'Kharmang|Katisho'                      => [35.0950, 75.8250],
        'Kharmang|Tolti'                        => [34.9950, 75.9650],
        'Kharmang|Baghicha'                     => [34.9650, 76.0050],
        'Kharmang|Kharmang Khas'                => [34.9469, 76.0219],
        'Kharmang|Hamzigon'                     => [34.8950, 76.0850],
        'Kharmang|Olding'                       => [34.7850, 76.1650],
        'Kharmang|Bresil'                       => [34.8350, 76.1250],

        // --- SHIGAR DISTRICT ---
        'Shigar|MC Shigar'                      => [35.4247, 75.7389],
        'Shigar|UC Gulabpur'                    => [35.5150, 75.6150],
        'Shigar|UC Wazirpur'                    => [35.4650, 75.6950],
        'Shigar|UC Chutron'                     => [35.6850, 75.4350],
        'Shigar|UC Tissar'                      => [35.5950, 75.5350],
        'Shigar|UC Churka'                      => [35.4850, 75.7150],
        'Shigar|UC Hashupi'                     => [35.5250, 75.6850],
        'Shigar|UC Alchori'                     => [35.5650, 75.6450],
        'Shigar|UC Basha'                       => [35.6450, 75.4850],
        'Shigar|UC Dasso'                       => [35.6350, 75.6950],
        'Shigar|UC Braldo'                      => [35.6950, 75.8250],
    ];

    /**
     * Build 3-level geographic cluster data (Districts, Tehsils/Sub-Divisions, Union Councils)
     * from FarmerOrganization records.
     */
    public static function buildMapClusters($records): array
    {
        $districts = [];
        $tehsils = [];
        $ucs = [];

        foreach ($records as $org) {
            $dName = trim((string) $org->district_name);
            $sheetCode = trim((string) $org->sheet_code);
            $subName = self::normalizeSubDivision((string) $org->sub_division, $dName);
            $ucName = self::normalizeUc((string) $org->uc_mc, $subName);
            $isFormed = (bool) $org->is_vo_formed;

            // 1. District aggregation
            if (! isset($districts[$dName])) {
                $dCoords = self::DISTRICTS[$dName] ?? [35.8000, 74.8000];
                $districts[$dName] = [
                    'level' => 'district',
                    'name' => $dName,
                    'sheet_code' => $sheetCode,
                    'division' => $org->division_name,
                    'lat' => $dCoords[0],
                    'lng' => $dCoords[1],
                    'total' => 0,
                    'formed' => 0,
                    'not_formed' => 0,
                ];
            }
            $districts[$dName]['total']++;
            if ($isFormed) {
                $districts[$dName]['formed']++;
            } else {
                $districts[$dName]['not_formed']++;
            }

            // 2. Tehsil / Sub-Division aggregation
            $tKey = $dName . '|' . $subName;
            if (! isset($tehsils[$tKey])) {
                $tCoords = self::TEHSILS[$tKey] ?? self::fallbackOffset(self::DISTRICTS[$dName] ?? [35.8, 74.8], $tKey, 0.08);
                $tehsils[$tKey] = [
                    'level' => 'tehsil',
                    'name' => $subName,
                    'district' => $dName,
                    'sheet_code' => $sheetCode,
                    'lat' => $tCoords[0],
                    'lng' => $tCoords[1],
                    'total' => 0,
                    'formed' => 0,
                    'not_formed' => 0,
                ];
            }
            $tehsils[$tKey]['total']++;
            if ($isFormed) {
                $tehsils[$tKey]['formed']++;
            } else {
                $tehsils[$tKey]['not_formed']++;
            }

            // 3. Union Council / MC aggregation
            $uKey = $dName . '|' . $ucName;
            if (! isset($ucs[$uKey])) {
                $parentCoords = self::TEHSILS[$tKey] ?? (self::DISTRICTS[$dName] ?? [35.8, 74.8]);
                $uCoords = self::UNION_COUNCILS[$uKey] ?? self::fallbackOffset($parentCoords, $uKey, 0.035);
                $ucs[$uKey] = [
                    'level' => 'uc',
                    'name' => $ucName,
                    'tehsil' => $subName,
                    'district' => $dName,
                    'sheet_code' => $sheetCode,
                    'lat' => $uCoords[0],
                    'lng' => $uCoords[1],
                    'total' => 0,
                    'formed' => 0,
                    'not_formed' => 0,
                    'sample_villages' => [],
                ];
            }
            $ucs[$uKey]['total']++;
            if ($isFormed) {
                $ucs[$uKey]['formed']++;
            } else {
                $ucs[$uKey]['not_formed']++;
            }
            if (count($ucs[$uKey]['sample_villages']) < 6) {
                $ucs[$uKey]['sample_villages'][] = $org->village_name . ($org->organization_name ? ' (' . $org->organization_name . ')' : '');
            }
        }

        return [
            'districts' => array_values($districts),
            'tehsils'   => array_values($tehsils),
            'ucs'       => array_values($ucs),
        ];
    }

    /**
     * Build cascading filter options (District -> Tehsil -> UC) and page-wide filtered analytics
     * (KPI totals, District summary rows, drill-down chart rows, and geospatial map clusters).
     */
    public static function buildPageAnalyticsAndFilters($allRecords, array $filters): array
    {
        $activeDivision = trim((string) ($filters['division'] ?? ''));
        $activeSheet    = trim((string) ($filters['sheet'] ?? ''));
        $activeDistrict = trim((string) ($filters['district'] ?? ''));
        $activeTehsil   = trim((string) ($filters['tehsil'] ?? ''));
        $activeUc       = trim((string) ($filters['uc'] ?? ''));
        $voStatus       = trim((string) ($filters['vo_status'] ?? ''));
        $search         = trim((string) ($filters['q'] ?? ''));

        if ($activeSheet !== '' && isset(FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet])) {
            $activeDistrict = FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet]['district'];
        } elseif ($activeDistrict !== '') {
            $activeSheet = FarmerOrganization::resolveSheetCode($activeDistrict) ?? '';
        }

        $tehsilOptions = [];
        $ucOptions = [];
        $rawTehsilMap = [];
        $rawUcMap = [];
        $districtTotalMap = [];

        foreach ($allRecords as $org) {
            $dName = trim((string) $org->district_name);
            $sCode = trim((string) $org->sheet_code);
            $rawSub = trim((string) $org->sub_division);
            $rawUc  = trim((string) $org->uc_mc);

            $normSub = self::normalizeSubDivision($rawSub, $dName);
            $normUc  = self::normalizeUc($rawUc, $normSub);

            $districtTotalMap[$sCode] = ($districtTotalMap[$sCode] ?? 0) + 1;

            $rawTehsilMap[$normSub][$rawSub] = $rawSub;
            $rawUcMap[$normUc][$rawUc] = $rawUc;

            $tKey = $dName . '|' . $normSub;
            if (! isset($tehsilOptions[$tKey])) {
                $tehsilOptions[$tKey] = [
                    'name' => $normSub,
                    'district' => $dName,
                    'sheet_code' => $sCode,
                    'division' => $org->division_name,
                    'count' => 0,
                ];
            }
            $tehsilOptions[$tKey]['count']++;

            $uKey = $dName . '|' . $normSub . '|' . $normUc;
            if (! isset($ucOptions[$uKey])) {
                $ucOptions[$uKey] = [
                    'name' => $normUc,
                    'tehsil' => $normSub,
                    'district' => $dName,
                    'sheet_code' => $sCode,
                    'division' => $org->division_name,
                    'count' => 0,
                ];
            }
            $ucOptions[$uKey]['count']++;
        }

        // Filter records in memory for whole-page KPIs, Summary Table, Charts, and Map
        $filteredRecords = $allRecords->filter(function ($org) use (
            $activeDivision,
            $activeDistrict,
            $activeTehsil,
            $activeUc,
            $voStatus,
            $search
        ) {
            $dName = trim((string) $org->district_name);
            $normSub = self::normalizeSubDivision((string) $org->sub_division, $dName);
            $normUc  = self::normalizeUc((string) $org->uc_mc, $normSub);

            if ($activeDivision !== '' && strcasecmp((string) $org->division_name, $activeDivision) !== 0) {
                return false;
            }
            if ($activeDistrict !== '' && strcasecmp($dName, $activeDistrict) !== 0) {
                return false;
            }
            if ($activeTehsil !== '' && strcasecmp($normSub, $activeTehsil) !== 0) {
                return false;
            }
            if ($activeUc !== '' && strcasecmp($normUc, $activeUc) !== 0) {
                return false;
            }
            if ($voStatus === 'formed' && ! $org->is_vo_formed) {
                return false;
            }
            if ($voStatus === 'not_formed' && $org->is_vo_formed) {
                return false;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    $org->village_name,
                    $org->organization_name,
                    $org->sub_division,
                    $org->uc_mc,
                    $org->focal_person_name,
                    $org->focal_person_phone,
                    $org->focal_person_raw,
                    $org->district_name,
                ]));
                if (! str_contains($haystack, strtolower($search))) {
                    return false;
                }
            }

            return true;
        })->values();

        // Calculate Filtered Totals
        $totalVillages = $filteredRecords->count();
        $vosFormed = $filteredRecords->where('is_vo_formed', true)->count();
        $noVosFormed = $totalVillages - $vosFormed;
        $coveragePct = $totalVillages > 0 ? round(($vosFormed / $totalVillages) * 100, 1) : 0;

        $totals = [
            'total_villages' => $totalVillages,
            'vos_formed' => $vosFormed,
            'no_vos_formed' => $noVosFormed,
            'coverage_pct' => $coveragePct,
            'unfiltered_total' => $allRecords->count(),
            'district_totals_unfiltered' => $districtTotalMap,
        ];

        // Build Summary Rows & Chart Breakdown Rows
        // 1. District Summary Rows (filtered by active filters)
        $bySheet = $filteredRecords->groupBy('sheet_code');
        $summaryRows = [];
        $sr = 1;
        foreach (FarmerOrganization::SHEET_DISTRICT_MAP as $code => $meta) {
            if ($activeDivision !== '' && strcasecmp($meta['division'], $activeDivision) !== 0) {
                continue;
            }
            if ($activeDistrict !== '' && strcasecmp($meta['district'], $activeDistrict) !== 0) {
                continue;
            }
            $group = $bySheet->get($code, collect());
            $vTotal = $group->count();
            $vFormed = $group->where('is_vo_formed', true)->count();
            $vNotFormed = $vTotal - $vFormed;
            $pct = $vTotal > 0 ? round(($vFormed / $vTotal) * 100, 1) : 0;

            $summaryRows[] = [
                'sr' => $sr++,
                'sheet_code' => $code,
                'district' => $meta['district'],
                'division' => $meta['division'],
                'total_villages' => $vTotal,
                'vos_formed' => $vFormed,
                'no_vos_formed' => $vNotFormed,
                'coverage_pct' => $pct,
            ];
        }

        // 2. Dynamic Drill-Down Breakdown (for Bar Chart & Sub-Summary when District or Tehsil is selected)
        $chartLevelLabel = 'District-Wise';
        $chartRows = [];
        if ($activeTehsil !== '' || $activeUc !== '') {
            $chartLevelLabel = 'Union Council (UC) Wise — ' . ($activeTehsil ?: $activeDistrict);
            $groupedUc = $filteredRecords->groupBy(function ($org) {
                $d = trim((string) $org->district_name);
                $s = self::normalizeSubDivision((string) $org->sub_division, $d);
                return self::normalizeUc((string) $org->uc_mc, $s);
            });
            $idx = 1;
            foreach ($groupedUc as $ucLabel => $items) {
                $vt = $items->count();
                $vf = $items->where('is_vo_formed', true)->count();
                $vn = $vt - $vf;
                $first = $items->first();
                $d = trim((string) $first->district_name);
                $s = self::normalizeSubDivision((string) $first->sub_division, $d);
                $chartRows[] = [
                    'sr' => $idx++,
                    'label' => $ucLabel,
                    'parent' => $s . ' (' . $d . ')',
                    'district' => $d,
                    'sheet_code' => $first->sheet_code,
                    'tehsil' => $s,
                    'uc' => $ucLabel,
                    'total_villages' => $vt,
                    'vos_formed' => $vf,
                    'no_vos_formed' => $vn,
                    'coverage_pct' => $vt > 0 ? round(($vf / $vt) * 100, 1) : 0,
                ];
            }
        } elseif ($activeDistrict !== '') {
            $chartLevelLabel = 'Tehsil / Sub-Division Wise — ' . $activeDistrict . ' District';
            $groupedTehsil = $filteredRecords->groupBy(function ($org) {
                $d = trim((string) $org->district_name);
                return self::normalizeSubDivision((string) $org->sub_division, $d);
            });
            $idx = 1;
            foreach ($groupedTehsil as $tLabel => $items) {
                $vt = $items->count();
                $vf = $items->where('is_vo_formed', true)->count();
                $vn = $vt - $vf;
                $first = $items->first();
                $d = trim((string) $first->district_name);
                $chartRows[] = [
                    'sr' => $idx++,
                    'label' => $tLabel,
                    'parent' => $d . ' District',
                    'district' => $d,
                    'sheet_code' => $first->sheet_code,
                    'tehsil' => $tLabel,
                    'uc' => '',
                    'total_villages' => $vt,
                    'vos_formed' => $vf,
                    'no_vos_formed' => $vn,
                    'coverage_pct' => $vt > 0 ? round(($vf / $vt) * 100, 1) : 0,
                ];
            }
        } else {
            foreach ($summaryRows as $r) {
                $chartRows[] = [
                    'sr' => $r['sr'],
                    'label' => $r['district'],
                    'parent' => $r['division'] . ' Division',
                    'district' => $r['district'],
                    'sheet_code' => $r['sheet_code'],
                    'tehsil' => '',
                    'uc' => '',
                    'total_villages' => $r['total_villages'],
                    'vos_formed' => $r['vos_formed'],
                    'no_vos_formed' => $r['no_vos_formed'],
                    'coverage_pct' => $r['coverage_pct'],
                ];
            }
        }

        // 3. Build Map Clusters for the filtered dataset + compute initial map focus
        $mapClusters = self::buildMapClusters($filteredRecords);
        $initialCenter = [35.65, 74.85];
        $initialZoom = 8;
        $initialMode = 'auto';

        if ($activeUc !== '' && count($mapClusters['ucs']) > 0) {
            $firstUc = $mapClusters['ucs'][0];
            $initialCenter = [$firstUc['lat'], $firstUc['lng']];
            $initialZoom = 12;
        } elseif ($activeTehsil !== '' && count($mapClusters['tehsils']) > 0) {
            $firstTeh = $mapClusters['tehsils'][0];
            $initialCenter = [$firstTeh['lat'], $firstTeh['lng']];
            $initialZoom = 11;
        } elseif ($activeDistrict !== '' && count($mapClusters['districts']) > 0) {
            $firstDist = $mapClusters['districts'][0];
            $initialCenter = [$firstDist['lat'], $firstDist['lng']];
            $initialZoom = 10;
        }

        $mapClusters['initial_center'] = $initialCenter;
        $mapClusters['initial_zoom'] = $initialZoom;
        $mapClusters['initial_mode'] = $initialMode;

        return [
            'totals' => $totals,
            'summaryRows' => $summaryRows,
            'chartRows' => $chartRows,
            'chartLevelLabel' => $chartLevelLabel,
            'mapClusters' => $mapClusters,
            'tehsilOptions' => array_values($tehsilOptions),
            'ucOptions' => array_values($ucOptions),
            'rawTehsilMap' => $rawTehsilMap,
            'rawUcMap' => $rawUcMap,
            'resolvedDistrict' => $activeDistrict,
            'resolvedSheet' => $activeSheet,
        ];
    }

    /**
     * Apply District, Tehsil, UC, Division, Status, and Search filters to the Eloquent Directory Query.
     */
    public static function applyFiltersToQuery($query, array $filters, array $rawTehsilMap, array $rawUcMap): void
    {
        $activeDivision = trim((string) ($filters['division'] ?? ''));
        $activeSheet    = trim((string) ($filters['sheet'] ?? ''));
        $activeDistrict = trim((string) ($filters['district'] ?? ''));
        $activeTehsil   = trim((string) ($filters['tehsil'] ?? ''));
        $activeUc       = trim((string) ($filters['uc'] ?? ''));
        $voStatus       = trim((string) ($filters['vo_status'] ?? ''));
        $search         = trim((string) ($filters['q'] ?? ''));

        if ($activeSheet !== '' && isset(FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet])) {
            $query->where('sheet_code', $activeSheet);
        } elseif ($activeDistrict !== '') {
            $query->where('district_name', $activeDistrict);
        }

        if ($activeDivision !== '') {
            $query->where('division_name', $activeDivision);
        }

        if ($activeTehsil !== '') {
            $rawSubs = isset($rawTehsilMap[$activeTehsil]) ? array_values($rawTehsilMap[$activeTehsil]) : [$activeTehsil];
            $query->whereIn('sub_division', $rawSubs);
        }

        if ($activeUc !== '') {
            $rawUcs = isset($rawUcMap[$activeUc]) ? array_values($rawUcMap[$activeUc]) : [$activeUc];
            $query->whereIn('uc_mc', $rawUcs);
        }

        if ($voStatus === 'formed') {
            $query->where('is_vo_formed', true);
        } elseif ($voStatus === 'not_formed') {
            $query->where('is_vo_formed', false);
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('village_name', 'like', "%{$search}%")
                    ->orWhere('organization_name', 'like', "%{$search}%")
                    ->orWhere('sub_division', 'like', "%{$search}%")
                    ->orWhere('uc_mc', 'like', "%{$search}%")
                    ->orWhere('focal_person_name', 'like', "%{$search}%")
                    ->orWhere('focal_person_phone', 'like', "%{$search}%")
                    ->orWhere('focal_person_raw', 'like', "%{$search}%")
                    ->orWhere('district_name', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Deterministic radial offset if a new custom Tehsil/UC is added by Admin without hardcoded coordinates.
     */
    private static function fallbackOffset(array $center, string $seed, float $radius): array
    {
        $hash = crc32($seed);
        $angle = deg2rad($hash % 360);
        $dist = $radius * (0.4 + (($hash >> 8) % 60) / 100.0);

        return [
            round($center[0] + sin($angle) * $dist, 5),
            round($center[1] + cos($angle) * $dist, 5),
        ];
    }
}


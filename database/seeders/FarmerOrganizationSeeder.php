<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\FarmerOrganization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FarmerOrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $file = public_path('docs/farmer_organizations_gb.xlsx');
        if (! file_exists($file)) {
            $fallback = "C:\\Users\\USER\\Downloads\\list of Farmer's Organizations GB (1).xlsx";
            if (file_exists($fallback)) {
                $file = $fallback;
            } else {
                $this->command?->warn("Farmer Organizations Excel file not found at {$file}");
                return;
            }
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        // Build district name -> ID lookup
        $districtsMap = [];
        foreach (District::all() as $d) {
            $districtsMap[strtolower(trim($d->name))] = $d->id;
        }
        // Alias Kharmung -> Kharmang
        if (isset($districtsMap['kharmang'])) {
            $districtsMap['kharmung'] = $districtsMap['kharmang'];
        }

        $sheetMeta = FarmerOrganization::SHEET_DISTRICT_MAP;

        DB::transaction(function () use ($spreadsheet, $sheetMeta, $districtsMap) {
            FarmerOrganization::query()->delete();

            foreach ($sheetMeta as $sheetCode => $meta) {
                $sheet = $spreadsheet->getSheetByName($sheetCode);
                if (! $sheet) {
                    continue;
                }

                $rows = $sheet->toArray(null, true, false, false);
                $defaultDivision = $meta['division'];
                $defaultDistrict = $meta['district'];
                $districtId = $districtsMap[strtolower($defaultDistrict)] ?? null;

                $lastDivision = $defaultDivision;
                $lastDistrict = $defaultDistrict;
                $lastSubDivision = '';
                $lastUcMc = '';
                $lastOrgName = '';

                foreach ($rows as $rowIndex => $row) {
                    $c0 = $this->cleanText($row[0] ?? '');
                    $c1 = $this->cleanText($row[1] ?? '');
                    $c2 = $this->cleanText($row[2] ?? '');
                    $c3 = $this->cleanText($row[3] ?? '');
                    $c4 = $this->cleanText($row[4] ?? ''); // Village Name
                    $c5 = $this->cleanText($row[5] ?? ''); // Organization Name
                    $c6 = $this->cleanText($row[6] ?? ''); // Focal Person & Contact

                    // Skip header rows or empty rows without village name
                    if ($c4 === '' || strtolower($c0) === 'division' || str_contains(strtolower($c4), 'name of village')) {
                        continue;
                    }

                    // Clean trailing commas from village name
                    $villageName = rtrim($c4, ', ');
                    if ($villageName === '') {
                        continue;
                    }

                    // Forward-fill Division, District, Sub Division
                    $division = ($c0 !== '' && ! $this->isDitto($c0)) ? $c0 : $lastDivision;
                    if (strtolower($division) === 'skardu') {
                        $division = 'Baltistan';
                    }
                    $lastDivision = $division;

                    $district = $defaultDistrict;
                    $lastDistrict = $district;

                    if ($c2 !== '' && ! $this->isDitto($c2)) {
                        $lastSubDivision = $c2;
                    }
                    $subDivision = $lastSubDivision;

                    if ($c3 !== '' && ! $this->isDitto($c3)) {
                        $lastUcMc = $c3;
                    }
                    $ucMc = $lastUcMc;

                    // Resolve Organization Name (handle "Do" / "-do-" / "Not formed yet")
                    $orgName = $c5;
                    if ($this->isDitto($orgName)) {
                        $orgName = $lastOrgName;
                    } elseif ($orgName !== '') {
                        $lastOrgName = $orgName;
                    }

                    // Determine if VO is formed
                    $orgLower = strtolower($orgName);
                    $isFormed = true;
                    if (
                        $orgName === ''
                        || $orgName === '-'
                        || str_contains($orgLower, 'not formed')
                        || str_contains($orgLower, 'no vo')
                        || $orgLower === 'nil'
                        || $orgLower === 'none'
                    ) {
                        $isFormed = false;
                        $orgName = null;
                    }

                    // Parse focal person name & phone
                    [$fpName, $fpPhone] = $this->parseFocalPerson($c6, $orgName);

                    FarmerOrganization::create([
                        'district_id' => $districtId,
                        'sheet_code' => $sheetCode,
                        'division_name' => $division ?: $defaultDivision,
                        'district_name' => $district,
                        'sub_division' => $subDivision ?: null,
                        'uc_mc' => $ucMc ?: null,
                        'village_name' => $villageName,
                        'organization_name' => $orgName,
                        'is_vo_formed' => $isFormed,
                        'focal_person_name' => $fpName,
                        'focal_person_phone' => $fpPhone,
                        'focal_person_raw' => ($c6 !== '' && $c6 !== '-') ? $c6 : null,
                        'is_active' => true,
                    ]);
                }
            }
        });
    }

    private function cleanText(?string $val): string
    {
        if ($val === null) {
            return '';
        }
        // Replace non-breaking spaces (\u00a0) and collapse multiple spaces
        $cleaned = str_replace(["\xc2\xa0", "\xa0"], ' ', $val);
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned);
        return trim($cleaned);
    }

    private function isDitto(string $val): bool
    {
        $v = strtolower(trim($val, " .-\t\n\r\0\x0B"));
        return in_array($v, ['do', '-do-', 'ditto', '"'], true);
    }

    private function parseFocalPerson(string $raw, ?string &$orgName): array
    {
        $raw = trim($raw, " -:\t");
        if ($raw === '') {
            return [null, null];
        }

        // Remove leading numbering like "2. "
        $rawClean = preg_replace('/^\d+\.\s*/', '', $raw);

        // Extract phone numbers (e.g. 03429777892, 0347-2013983, +923129765022, 3555118897, (03555250800))
        if (preg_match('/(\+?92[\d\-\s]{9,13}|0?3\d{2}[\-\s]?\d{7,8})/u', $rawClean, $matches)) {
            $phone = trim($matches[1]);
            // Normalize 10-digit mobile starting with 3 (e.g. 3555118897 -> 03555118897)
            $digitsOnly = preg_replace('/\D/', '', $phone);
            if (strlen($digitsOnly) === 10 && str_starts_with($digitsOnly, '3')) {
                $phone = '0' . $digitsOnly;
            }

            $namePart = str_replace($matches[0], '', $rawClean);
            $namePart = trim($namePart, " ():,-/\t");

            return [$namePart !== '' ? $namePart : null, $phone];
        }

        // If raw is only a name without phone
        return [$rawClean !== '' ? $rawClean : null, null];
    }
}


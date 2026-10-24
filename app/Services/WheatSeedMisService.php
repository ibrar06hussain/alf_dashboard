<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\District;
use App\Models\Scheme;
use App\Models\SchemeDistrict;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Cnic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WheatSeedMisService
{
    public const SCHEME_ID = 1;
    public const SCHEME_SLUG = 'distribution-of-wheat-seed-cost-sharing';
    public const FORM_ID = 1;
    public const BAG_WEIGHT_KG = 50;
    public const KANALS_PER_ACRE = 8;
    public const KANALS_PER_BAG = 4; // Agronomic baseline: 1 bag of 50kg per 4 kanals (12.5 kg/kanal)

    public const DIVISIONS = [
        'Gilgit Division' => ['Gilgit', 'Hunza', 'Nagar', 'Ghizer'],
        'Baltistan Division' => ['Skardu', 'Ghanche', 'Shigar', 'Kharmang', 'Roundu'],
        'Diamer Division' => ['Diamer', 'Astore', 'Darel', 'Tangir'],
    ];

    /**
     * Resolve Division name by District name.
     */
    public static function divisionForDistrict(?string $districtName): string
    {
        if (! $districtName) {
            return 'Other / Unassigned';
        }

        foreach (self::DIVISIONS as $division => $districts) {
            if (in_array($districtName, $districts, true)) {
                return $division;
            }
        }

        return 'Other / Unassigned';
    }

    /**
     * Get the Wheat Seed Scheme model.
     */
    public function getScheme(): ?Scheme
    {
        return Scheme::where('id', self::SCHEME_ID)
            ->orWhere('slug', self::SCHEME_SLUG)
            ->first();
    }

    /**
     * Get the configured per-bag rate (PKR) from SiteSetting.
     * Returns null if unconfigured (never guess or assume a price).
     */
    public function getConfiguredBagRate(): ?float
    {
        $raw = SiteSetting::getValue('wheat_seed_bag_rate');
        if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw > 0) {
            return (float) $raw;
        }

        return null;
    }

    /**
     * Get the configured seed bag weight (kg). Defaults to 50kg.
     */
    public function getConfiguredBagWeight(): float
    {
        $raw = SiteSetting::getValue('wheat_seed_bag_weight_kg');
        if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw > 0) {
            return (float) $raw;
        }

        return (float) self::BAG_WEIGHT_KG;
    }

    /**
     * Get the government cost-share percentage. Defaults to 50%.
     */
    public function getConfiguredGovtSharePct(): float
    {
        $raw = SiteSetting::getValue('wheat_seed_govt_share_pct');
        if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 100) {
            return (float) $raw;
        }

        return 50.0;
    }

    /**
     * Get the farmer contribution percentage. Defaults to 50%.
     */
    public function getConfiguredFarmerSharePct(): float
    {
        $raw = SiteSetting::getValue('wheat_seed_farmer_share_pct');
        if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 100) {
            return (float) $raw;
        }

        return 50.0;
    }

    /**
     * Get the baseline agronomic sowing rate (Kanals per bag). Defaults to 4 kanals.
     */
    public function getConfiguredKanalsPerBag(): float
    {
        $raw = SiteSetting::getValue('wheat_seed_kanals_per_bag');
        if ($raw !== null && $raw !== '' && is_numeric($raw) && (float) $raw > 0) {
            return (float) $raw;
        }

        return (float) self::KANALS_PER_BAG;
    }

    /**
     * Get the seed variety name / specification.
     */
    public function getConfiguredSeedVariety(): ?string
    {
        $raw = SiteSetting::getValue('wheat_seed_variety_name');
        return ! empty($raw) ? (string) $raw : null;
    }

    /**
     * Get the scheme target season / fiscal year.
     */
    public function getConfiguredFiscalYear(): ?string
    {
        $raw = SiteSetting::getValue('wheat_seed_fiscal_year');
        return ! empty($raw) ? (string) $raw : null;
    }

    /**
     * Get configured district rates and transport costs map.
     * Keyed by district_id.
     */
    public function getRawDistrictRates(): array
    {
        $raw = SiteSetting::getValue('wheat_seed_district_rates');
        if (! empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Get effective delivered per-bag rate (PKR) for a given district.
     * Incorporates baseline rate plus district-specific transportation carriage cost.
     */
    public function getRateForDistrict(?int $districtId): ?float
    {
        $baseRate = $this->getConfiguredBagRate();
        if (! $districtId) {
            return $baseRate;
        }

        $rates = $this->getRawDistrictRates();
        $distData = $rates[$districtId] ?? $rates[(string) $districtId] ?? null;

        if ($distData !== null) {
            // Explicit delivered rate override
            if (isset($distData['rate']) && is_numeric($distData['rate']) && (float) $distData['rate'] > 0) {
                return (float) $distData['rate'];
            }
            // Base rate + transport charge
            if ($baseRate !== null && isset($distData['transport_cost']) && is_numeric($distData['transport_cost'])) {
                return round($baseRate + (float) $distData['transport_cost'], 2);
            }
        }

        return $baseRate;
    }

    /**
     * Get transportation charge per bag (PKR) for a given district.
     */
    public function getTransportCostForDistrict(?int $districtId): float
    {
        if (! $districtId) {
            return 0.0;
        }

        $rates = $this->getRawDistrictRates();
        $distData = $rates[$districtId] ?? $rates[(string) $districtId] ?? null;

        if ($distData !== null && isset($distData['transport_cost']) && is_numeric($distData['transport_cost'])) {
            return (float) $distData['transport_cost'];
        }

        $baseRate = $this->getConfiguredBagRate();
        if ($baseRate !== null && isset($distData['rate']) && is_numeric($distData['rate']) && (float) $distData['rate'] > $baseRate) {
            return round((float) $distData['rate'] - $baseRate, 2);
        }

        return 0.0;
    }

    /**
     * Get district rates bundle for all districts in the system.
     */
    public function getAllDistrictRatesTable(): Collection
    {
        $districts = District::orderBy('name')->get();
        $baseRate = $this->getConfiguredBagRate();
        $rawRates = $this->getRawDistrictRates();
        $govPct = $this->getConfiguredGovtSharePct();
        $farmerPct = $this->getConfiguredFarmerSharePct();

        return $districts->map(function ($dist) use ($baseRate, $rawRates, $govPct, $farmerPct) {
            $data = $rawRates[$dist->id] ?? $rawRates[(string) $dist->id] ?? [];
            $transport = isset($data['transport_cost']) && is_numeric($data['transport_cost']) ? (float) $data['transport_cost'] : 0.0;

            $rate = null;
            if (isset($data['rate']) && is_numeric($data['rate']) && (float) $data['rate'] > 0) {
                $rate = (float) $data['rate'];
            } elseif ($baseRate !== null) {
                $rate = round($baseRate + $transport, 2);
            }

            $govShare = $rate !== null ? round($rate * ($govPct / 100), 2) : null;
            $farmerShare = $rate !== null ? round($rate * ($farmerPct / 100), 2) : null;

            return (object) [
                'district_id' => $dist->id,
                'district_name' => $dist->name,
                'division' => self::divisionForDistrict($dist->name),
                'transport_cost' => $transport,
                'delivered_rate' => $rate,
                'gov_share' => $govShare,
                'farmer_share' => $farmerShare,
            ];
        });
    }

    /**
     * Get all seed data configuration settings bundled.
     */
    public function getSeedDataSettings(): array
    {
        $rawDistrictRates = $this->getRawDistrictRates();
        $baseRate = $this->getConfiguredBagRate();

        return [
            'bag_rate' => $baseRate,
            'bag_weight_kg' => $this->getConfiguredBagWeight(),
            'govt_share_pct' => $this->getConfiguredGovtSharePct(),
            'farmer_share_pct' => $this->getConfiguredFarmerSharePct(),
            'kanals_per_bag' => $this->getConfiguredKanalsPerBag(),
            'variety_name' => $this->getConfiguredSeedVariety() ?? 'Certified Wheat Seed',
            'fiscal_year' => $this->getConfiguredFiscalYear() ?? 'Rabi 2025-2026',
            'district_rates' => $rawDistrictRates,
            'has_district_varying_rates' => ! empty($rawDistrictRates),
        ];
    }


    /**
     * Build the filtered base query for Wheat Seed applications.
     */
    public function getFilteredQuery(array $filters = [], ?User $user = null): Builder
    {
        $scheme = $this->getScheme();
        $schemeId = $scheme?->id ?? self::SCHEME_ID;

        $query = Application::query()
            ->where('applications.scheme_id', $schemeId)
            ->with(['applicant', 'district', 'statusLogs.user']);

        // Staff role scoping
        if ($user) {
            if ($user->hasRole('District Officer') && $user->district_id) {
                $query->where('applications.district_id', $user->district_id);
            }
        }

        // Filter: Date range
        if (! empty($filters['from_date'])) {
            $query->whereDate('applications.created_at', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('applications.created_at', '<=', $filters['to_date']);
        }

        // Filter: Status / Decision
        if (! empty($filters['status'])) {
            $query->where('applications.status', $filters['status']);
        }

        // Filter: District
        if (! empty($filters['district_id'])) {
            $query->where('applications.district_id', (int) $filters['district_id']);
        }

        // Filter: Division
        if (! empty($filters['division'])) {
            $divisionDistricts = self::DIVISIONS[$filters['division']] ?? [];
            if (! empty($divisionDistricts)) {
                $query->whereHas('district', function ($dq) use ($divisionDistricts) {
                    $dq->whereIn('name', $divisionDistricts);
                });
            }
        }

        // Filter: Tehsil, UC, Village
        if (! empty($filters['tehsil'])) {
            $query->whereHas('applicant', fn ($q) => $q->where('tehsil', $filters['tehsil']));
        }
        if (! empty($filters['union_council'])) {
            $query->whereHas('applicant', fn ($q) => $q->where('union_council', $filters['union_council']));
        }
        if (! empty($filters['village'])) {
            $query->whereHas('applicant', fn ($q) => $q->where('village', $filters['village']));
        }

        // Filter: Reviewing Officer (status logs)
        if (! empty($filters['officer_id'])) {
            $officerId = (int) $filters['officer_id'];
            $query->whereHas('statusLogs', fn ($q) => $q->where('user_id', $officerId));
        }

        // Filter: Survey Status
        if (! empty($filters['survey_status'])) {
            if ($filters['survey_status'] === 'verified') {
                $query->whereIn('applications.status', ['district_verified', 'directorate_approved', 'disbursed'])
                    ->orWhereNotNull('applications.verification_note');
            } elseif ($filters['survey_status'] === 'pending_survey') {
                $query->where('applications.status', 'pending')
                    ->whereNull('applications.verification_note');
            }
        }

        // Filter: Available Land (Kanals)
        $minLand = $filters['min_land'] ?? $filters['land_kanals'] ?? null;
        if ($minLand !== null && $minLand !== '' && is_numeric($minLand)) {
            $val = (float) $minLand;
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$val]);
            });
        }
        if (! empty($filters['max_land']) && is_numeric($filters['max_land'])) {
            $val = (float) $filters['max_land'];
            $query->whereHas('fieldValues', function ($fq) use ($val) {
                $fq->where(function ($sub) {
                    $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                        ->orWhere('form_field_id', 8);
                })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$val]);
            });
        }

        // Filter: Bags Required (50kg)
        $minBags = $filters['min_bags'] ?? $filters['bags_required'] ?? null;
        if ($minBags !== null && $minBags !== '' && is_numeric($minBags)) {
            $val = (int) $minBags;
            $kanalsPerBag = $this->getConfiguredKanalsPerBag();
            $minLandForBags = max(0, ($val - 1) * $kanalsPerBag + 0.01);
            $query->where(function ($bq) use ($val, $minLandForBags) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) >= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($minLandForBags) {
                    $wheatQ->whereHas('fieldValues', function ($fq) use ($minLandForBags) {
                        $fq->where(function ($sub) {
                            $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                ->orWhere('form_field_id', 8);
                        })->whereRaw('CAST(value AS DECIMAL(10,2)) >= ?', [$minLandForBags]);
                    });
                });
            });
        }
        if (! empty($filters['max_bags']) && is_numeric($filters['max_bags'])) {
            $val = (int) $filters['max_bags'];
            $kanalsPerBag = $this->getConfiguredKanalsPerBag();
            $maxLandForBags = $val * $kanalsPerBag;
            $query->where(function ($bq) use ($val, $maxLandForBags) {
                $bq->whereHas('fieldValues', function ($fq) use ($val) {
                    $fq->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['bags_required', 'seed_bags', 'seed_bags_required', 'wheat_seed_bags']))
                       ->whereRaw('CAST(value AS UNSIGNED) <= ?', [$val]);
                })->orWhere(function ($wheatQ) use ($maxLandForBags) {
                    $wheatQ->whereHas('fieldValues', function ($fq) use ($maxLandForBags) {
                        $fq->where(function ($sub) {
                            $sub->whereHas('formField', fn ($ff) => $ff->whereIn('field_key', ['land_kanals', 'available_land']))
                                ->orWhere('form_field_id', 8);
                        })->whereRaw('CAST(value AS DECIMAL(10,2)) <= ?', [$maxLandForBags]);
                    });
                });
            });
        }

        // Filter: Search Keyword (Application No, CNIC, Name, Phone, Village)
        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $cleanCnic = Cnic::clean($q);

            $query->where(function ($builder) use ($q, $cleanCnic) {
                $builder->where('applications.application_no', 'like', "%{$q}%")
                    ->orWhereHas('applicant', function ($aq) use ($q, $cleanCnic) {
                        $aq->where('full_name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%")
                            ->orWhere('tehsil', 'like', "%{$q}%")
                            ->orWhere('union_council', 'like', "%{$q}%")
                            ->orWhere('village', 'like', "%{$q}%");

                        if (! empty($cleanCnic)) {
                            $aq->orWhere('cnic', 'like', "%{$cleanCnic}%");
                        }
                    });
            });
        }

        return $query;
    }

    /**
     * Compute comprehensive scheme KPIs and summary metrics.
     */
    public function getKpis(array $filters = [], ?User $user = null): array
    {
        $query = $this->getFilteredQuery($filters, $user);
        $applications = (clone $query)->with(['fieldValues.formField'])->get();

        $totalApplications = $applications->count();

        // Status Counts
        $statusCounts = [
            'pending' => 0,
            'district_verified' => 0,
            'directorate_approved' => 0,
            'disbursed' => 0,
            'rejected' => 0,
        ];
        foreach ($applications as $app) {
            $st = (string) $app->status;
            if (isset($statusCounts[$st])) {
                $statusCounts[$st]++;
            }
        }

        // Survey Status breakdown
        $surveyVerified = 0;
        $surveyPending = 0;
        foreach ($applications as $app) {
            if (in_array($app->status, ['district_verified', 'directorate_approved', 'disbursed'], true) || ! empty($app->verification_note)) {
                $surveyVerified++;
            } else {
                $surveyPending++;
            }
        }

        // Land, Cultivation, and Bags Aggregation
        $totalAvailableKanals = 0.0;
        $totalCultivatedKanals = 0.0;
        $hasCultivatedData = false;

        $totalRequestedBags = 0;
        $totalApprovedBags = 0;
        $totalDisbursedBags = 0;

        $uniqueVillages = [];

        $bagRate = $this->getConfiguredBagRate();
        $totalApprovedCost = 0.0;
        $totalDisbursedCost = 0.0;
        $totalApprovedTransport = 0.0;
        $districtRatesUsed = [];

        foreach ($applications as $app) {
            $parsed = $this->extractAppAttributes($app);

            if ($parsed['land_kanals'] !== null) {
                $totalAvailableKanals += $parsed['land_kanals'];
            }

            if ($parsed['cultivated_kanals'] !== null) {
                $totalCultivatedKanals += $parsed['cultivated_kanals'];
                $hasCultivatedData = true;
            }

            $reqBags = $parsed['requested_bags'];
            $totalRequestedBags += $reqBags;

            $distRate = $this->getRateForDistrict($app->district_id) ?? $bagRate;
            $distTransport = $this->getTransportCostForDistrict($app->district_id);

            if (in_array($app->status, ['directorate_approved', 'disbursed'], true)) {
                $totalApprovedBags += $reqBags;
                if ($distRate !== null && $reqBags > 0) {
                    $totalApprovedCost += ($reqBags * $distRate);
                    $totalApprovedTransport += ($reqBags * $distTransport);
                    $districtRatesUsed[$app->district_id ?? 0] = $distRate;
                }
            }

            if ($app->status === 'disbursed') {
                $totalDisbursedBags += $reqBags;
                if ($distRate !== null && $reqBags > 0) {
                    $totalDisbursedCost += ($reqBags * $distRate);
                }
            }

            if (! empty($parsed['village'])) {
                $uniqueVillages[$parsed['village']] = true;
            }
        }

        // Allocated Quota across relevant districts
        $schemeId = $this->getScheme()?->id ?? self::SCHEME_ID;
        $quotaQuery = SchemeDistrict::query()->where('scheme_id', $schemeId);

        if (! empty($filters['district_id'])) {
            $quotaQuery->where('district_id', (int) $filters['district_id']);
        } elseif ($user && $user->hasRole('District Officer') && $user->district_id) {
            $quotaQuery->where('district_id', $user->district_id);
        } elseif (! empty($filters['division'])) {
            $divisionDistricts = self::DIVISIONS[$filters['division']] ?? [];
            if (! empty($divisionDistricts)) {
                $quotaQuery->whereHas('district', fn ($q) => $q->whereIn('name', $divisionDistricts));
            }
        }
        $totalAllocatedBags = (int) $quotaQuery->sum('quota');

        // Land Conversions
        $totalAvailableAcres = round($totalAvailableKanals / self::KANALS_PER_ACRE, 2);
        $totalCultivatedAcres = $hasCultivatedData ? round($totalCultivatedKanals / self::KANALS_PER_ACRE, 2) : null;

        // Dynamic Seed Data Configurations
        $bagWeight = $this->getConfiguredBagWeight();
        $govShareFraction = $this->getConfiguredGovtSharePct() / 100;
        $farmerShareFraction = $this->getConfiguredFarmerSharePct() / 100;

        // Weights
        $weightRequestedKg = $totalRequestedBags * $bagWeight;
        $weightRequestedMt = round($weightRequestedKg / 1000, 2);

        $weightApprovedKg = $totalApprovedBags * $bagWeight;
        $weightApprovedMt = round($weightApprovedKg / 1000, 2);

        $weightAllocatedKg = $totalAllocatedBags * $bagWeight;
        $weightAllocatedMt = round($weightAllocatedKg / 1000, 2);

        $weightDisbursedKg = $totalDisbursedBags * $bagWeight;
        $weightDisbursedMt = round($weightDisbursedKg / 1000, 2);

        // Averages
        $avgBagsPerApplicant = $totalApplications > 0
            ? round($totalRequestedBags / $totalApplications, 2)
            : 0;

        $avgBagsPerKanal = $totalAvailableKanals > 0
            ? round($totalRequestedBags / $totalAvailableKanals, 4)
            : 0;

        $avgKanalsPerApplicant = $totalApplications > 0
            ? round($totalAvailableKanals / $totalApplications, 2)
            : 0;

        // Cost-sharing incorporating district-specific transportation & delivered rates
        $rawDistrictRates = $this->getRawDistrictRates();
        $isConfigured = $bagRate !== null || ! empty($rawDistrictRates);
        $costSharing = null;

        if ($isConfigured) {
            $totalSchemeCost = $totalApprovedCost;
            $govShare = round($totalSchemeCost * $govShareFraction, 2);
            $farmerShare = round($totalSchemeCost * $farmerShareFraction, 2);
            $disbursedCost = $totalDisbursedCost;
            $disbursedGovShare = round($disbursedCost * $govShareFraction, 2);

            $costSharing = [
                'rate_per_bag' => $bagRate,
                'rate_min' => ! empty($districtRatesUsed) ? min($districtRatesUsed) : $bagRate,
                'rate_max' => ! empty($districtRatesUsed) ? max($districtRatesUsed) : $bagRate,
                'total_transport_cost' => $totalApprovedTransport,
                'is_district_varying' => count(array_unique($districtRatesUsed)) > 1,
                'bag_weight_kg' => $bagWeight,
                'govt_share_pct' => $this->getConfiguredGovtSharePct(),
                'farmer_share_pct' => $this->getConfiguredFarmerSharePct(),
                'total_approved_value' => $totalSchemeCost,
                'gov_share_50' => $govShare,
                'gov_share' => $govShare,
                'farmer_share_50' => $farmerShare,
                'farmer_share' => $farmerShare,
                'disbursed_value' => $disbursedCost,
                'disbursed_gov_share' => $disbursedGovShare,
                'is_configured' => true,
            ];
        } else {
            $costSharing = [
                'rate_per_bag' => null,
                'rate_min' => null,
                'rate_max' => null,
                'total_transport_cost' => 0.0,
                'is_district_varying' => false,
                'bag_weight_kg' => $bagWeight,
                'govt_share_pct' => $this->getConfiguredGovtSharePct(),
                'farmer_share_pct' => $this->getConfiguredFarmerSharePct(),
                'total_approved_value' => null,
                'gov_share_50' => null,
                'gov_share' => null,
                'farmer_share_50' => null,
                'farmer_share' => null,
                'disbursed_value' => null,
                'disbursed_gov_share' => null,
                'is_configured' => false,
                'note' => 'Not yet configured / Not yet captured',
            ];
        }

        // Village coverage
        $uniqueVillageCount = count($uniqueVillages);
        $avgAppsPerVillage = $uniqueVillageCount > 0
            ? round($totalApplications / $uniqueVillageCount, 2)
            : 0;

        return [
            'total_applications' => $totalApplications,
            'status_counts' => $statusCounts,
            'survey_status' => [
                'verified' => $surveyVerified,
                'pending' => $surveyPending,
                'not_captured' => 0, // All submitted applications are either pending review or verified
            ],
            'land' => [
                'available_kanals' => $totalAvailableKanals,
                'available_acres' => $totalAvailableAcres,
                'cultivated_kanals_last_year' => $hasCultivatedData ? $totalCultivatedKanals : null,
                'cultivated_acres_last_year' => $totalCultivatedAcres,
                'has_cultivated_data' => $hasCultivatedData,
                'cultivated_display' => $hasCultivatedData ? number_format($totalCultivatedKanals, 1) . ' Kanals' : 'Not yet captured',
                'availability_vs_previous_ratio' => ($hasCultivatedData && $totalCultivatedKanals > 0)
                    ? round($totalAvailableKanals / $totalCultivatedKanals, 2)
                    : null,
            ],
            'bags' => [
                'requested' => $totalRequestedBags,
                'approved' => $totalApprovedBags,
                'allocated' => $totalAllocatedBags,
                'disbursed' => $totalDisbursedBags,
                'gap' => $totalAllocatedBags - $totalApprovedBags,
            ],
            'weights' => [
                'requested_kg' => $weightRequestedKg,
                'requested_mt' => $weightRequestedMt,
                'approved_kg' => $weightApprovedKg,
                'approved_mt' => $weightApprovedMt,
                'allocated_kg' => $weightAllocatedKg,
                'allocated_mt' => $weightAllocatedMt,
                'disbursed_kg' => $weightDisbursedKg,
                'disbursed_mt' => $weightDisbursedMt,
            ],
            'averages' => [
                'requested_bags_per_applicant' => $avgBagsPerApplicant,
                'bags_per_kanal' => $avgBagsPerKanal,
                'kanals_per_applicant' => $avgKanalsPerApplicant,
            ],
            'cost_sharing' => $costSharing,
            'village_coverage' => [
                'unique_villages' => $uniqueVillageCount,
                'avg_applications_per_village' => $avgAppsPerVillage,
            ],
        ];
    }

    /**
     * District-wise Demand-Supply Gap analysis with allocated quota.
     */
    public function getDemandSupplyGap(array $filters = [], ?User $user = null): Collection
    {
        $schemeId = $this->getScheme()?->id ?? self::SCHEME_ID;
        $districts = District::orderBy('name')->get();

        $apps = $this->getFilteredQuery($filters, $user)
            ->with(['fieldValues.formField'])
            ->get();

        $appAggregates = [];
        foreach ($apps as $app) {
            $dId = $app->district_id;
            if (! isset($appAggregates[$dId])) {
                $appAggregates[$dId] = [
                    'applications' => 0,
                    'requested_bags' => 0,
                    'approved_bags' => 0,
                    'disbursed_bags' => 0,
                    'available_kanals' => 0.0,
                ];
            }

            $parsed = $this->extractAppAttributes($app);
            $appAggregates[$dId]['applications']++;
            $appAggregates[$dId]['requested_bags'] += $parsed['requested_bags'];
            $appAggregates[$dId]['available_kanals'] += ($parsed['land_kanals'] ?? 0);

            if (in_array($app->status, ['directorate_approved', 'disbursed'], true)) {
                $appAggregates[$dId]['approved_bags'] += $parsed['requested_bags'];
            }
            if ($app->status === 'disbursed') {
                $appAggregates[$dId]['disbursed_bags'] += $parsed['requested_bags'];
            }
        }

        // Quotas
        $quotas = SchemeDistrict::where('scheme_id', $schemeId)->pluck('quota', 'district_id');
        $bagWeight = $this->getConfiguredBagWeight();
        $govShareFraction = $this->getConfiguredGovtSharePct() / 100;
        $farmerShareFraction = $this->getConfiguredFarmerSharePct() / 100;

        return $districts->map(function ($district) use ($appAggregates, $quotas, $bagWeight, $govShareFraction, $farmerShareFraction) {
            $dId = $district->id;
            $agg = $appAggregates[$dId] ?? [
                'applications' => 0,
                'requested_bags' => 0,
                'approved_bags' => 0,
                'disbursed_bags' => 0,
                'available_kanals' => 0.0,
            ];

            $quota = (int) ($quotas[$dId] ?? 0);
            $requested = (int) $agg['requested_bags'];
            $approved = (int) $agg['approved_bags'];
            $disbursed = (int) $agg['disbursed_bags'];
            $gap = $quota - $approved;

            $statusBadge = 'Balanced';
            if ($gap < 0) {
                $statusBadge = 'Deficit';
            } elseif ($gap > 0 && $approved > 0) {
                $statusBadge = 'Surplus Available';
            } elseif ($approved === 0 && $quota > 0) {
                $statusBadge = 'Unutilized Quota';
            }

            $districtRate = $this->getRateForDistrict($dId);
            $transportCost = $this->getTransportCostForDistrict($dId);
            $approvedCost = ($districtRate !== null && $approved > 0) ? round($approved * $districtRate, 2) : 0.0;
            $govSubsidy = ($approvedCost > 0) ? round($approvedCost * $govShareFraction, 2) : 0.0;
            $farmerShare = ($approvedCost > 0) ? round($approvedCost * $farmerShareFraction, 2) : 0.0;

            return [
                'district_id' => $dId,
                'district_name' => $district->name,
                'district_code' => $district->code,
                'division' => self::divisionForDistrict($district->name),
                'quota' => $quota,
                'applications' => $agg['applications'],
                'requested_bags' => $requested,
                'approved_bags' => $approved,
                'disbursed_bags' => $disbursed,
                'gap' => $gap,
                'status_badge' => $statusBadge,
                'available_kanals' => round($agg['available_kanals'], 1),
                'available_acres' => round($agg['available_kanals'] / self::KANALS_PER_ACRE, 2),
                'quota_mt' => round(($quota * $bagWeight) / 1000, 2),
                'approved_mt' => round(($approved * $bagWeight) / 1000, 2),
                'rate_per_bag' => $districtRate,
                'transport_cost' => $transportCost,
                'approved_cost' => $approvedCost,
                'gov_subsidy' => $govSubsidy,
                'farmer_share' => $farmerShare,
            ];
        });
    }

    /**
     * Hierarchical Division -> District -> Tehsil -> Union Council -> Village rollup.
     */
    public function getDivisionDistrictRollup(array $filters = [], ?User $user = null): array
    {
        $apps = $this->getFilteredQuery($filters, $user)
            ->with(['district', 'applicant', 'fieldValues.formField'])
            ->get();

        $tree = [];

        foreach ($apps as $app) {
            $distName = $app->district?->name ?? 'Unknown District';
            $divName = self::divisionForDistrict($distName);
            $tehsil = $app->applicant?->tehsil ?: 'Tehsil Unspecified';
            $uc = $app->applicant?->union_council ?: 'UC Unspecified';
            $village = $app->applicant?->village ?: 'Village Unspecified';

            $parsed = $this->extractAppAttributes($app);
            $kanals = $parsed['land_kanals'] ?? 0;
            $bags = $parsed['requested_bags'];

            if (! isset($tree[$divName])) {
                $tree[$divName] = ['total' => 0, 'kanals' => 0, 'bags' => 0, 'districts' => []];
            }
            if (! isset($tree[$divName]['districts'][$distName])) {
                $tree[$divName]['districts'][$distName] = ['total' => 0, 'kanals' => 0, 'bags' => 0, 'tehsils' => []];
            }
            if (! isset($tree[$divName]['districts'][$distName]['tehsils'][$tehsil])) {
                $tree[$divName]['districts'][$distName]['tehsils'][$tehsil] = ['total' => 0, 'kanals' => 0, 'bags' => 0, 'ucs' => []];
            }
            if (! isset($tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc])) {
                $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc] = ['total' => 0, 'kanals' => 0, 'bags' => 0, 'villages' => []];
            }
            if (! isset($tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['villages'][$village])) {
                $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['villages'][$village] = ['total' => 0, 'kanals' => 0, 'bags' => 0];
            }

            // Rollup sums
            $tree[$divName]['total']++;
            $tree[$divName]['kanals'] += $kanals;
            $tree[$divName]['bags'] += $bags;

            $tree[$divName]['districts'][$distName]['total']++;
            $tree[$divName]['districts'][$distName]['kanals'] += $kanals;
            $tree[$divName]['districts'][$distName]['bags'] += $bags;

            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['total']++;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['kanals'] += $kanals;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['bags'] += $bags;

            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['total']++;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['kanals'] += $kanals;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['bags'] += $bags;

            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['villages'][$village]['total']++;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['villages'][$village]['kanals'] += $kanals;
            $tree[$divName]['districts'][$distName]['tehsils'][$tehsil]['ucs'][$uc]['villages'][$village]['bags'] += $bags;
        }

        return $tree;
    }

    /**
     * Water availability donut and road accessibility bar chart data.
     */
    public function getWaterRoadBreakdowns(array $filters = [], ?User $user = null): array
    {
        $apps = $this->getFilteredQuery($filters, $user)
            ->with(['district', 'fieldValues.formField'])
            ->get();

        $waterCounts = ['Yes' => 0, 'No' => 0, 'Not yet captured' => 0];
        $waterByDistrict = [];

        $roadCounts = [
            'Main Road' => 0,
            'Link Road' => 0,
            'Pony Track' => 0,
            'Yes' => 0,
            'No' => 0,
            'Not yet captured' => 0,
        ];
        $roadByDistrict = [];

        foreach ($apps as $app) {
            $distName = $app->district?->name ?? 'Unknown';
            $parsed = $this->extractAppAttributes($app);

            // Water
            $wVal = $parsed['water_available'];
            if ($wVal === null || $wVal === '') {
                $wLabel = 'Not yet captured';
            } elseif (in_array(strtolower($wVal), ['yes', 'available', '1', 'true'], true)) {
                $wLabel = 'Yes';
            } elseif (in_array(strtolower($wVal), ['no', 'unavailable', '0', 'false'], true)) {
                $wLabel = 'No';
            } else {
                $wLabel = ucfirst($wVal);
            }

            if (! isset($waterCounts[$wLabel])) {
                $waterCounts[$wLabel] = 0;
            }
            $waterCounts[$wLabel]++;

            if (! isset($waterByDistrict[$distName])) {
                $waterByDistrict[$distName] = ['Yes' => 0, 'No' => 0, 'Not yet captured' => 0];
            }
            if (! isset($waterByDistrict[$distName][$wLabel])) {
                $waterByDistrict[$distName][$wLabel] = 0;
            }
            $waterByDistrict[$distName][$wLabel]++;

            // Road Access
            $rVal = $parsed['road_access'];
            if ($rVal === null || $rVal === '') {
                $rLabel = 'Not yet captured';
            } else {
                $lower = strtolower(trim($rVal));
                if (str_contains($lower, 'main')) {
                    $rLabel = 'Main Road';
                } elseif (str_contains($lower, 'link')) {
                    $rLabel = 'Link Road';
                } elseif (str_contains($lower, 'pony') || str_contains($lower, 'track')) {
                    $rLabel = 'Pony Track';
                } elseif ($lower === 'yes') {
                    $rLabel = 'Yes';
                } elseif ($lower === 'no') {
                    $rLabel = 'No';
                } else {
                    $rLabel = ucfirst($rVal);
                }
            }

            if (! isset($roadCounts[$rLabel])) {
                $roadCounts[$rLabel] = 0;
            }
            $roadCounts[$rLabel]++;

            if (! isset($roadByDistrict[$distName])) {
                $roadByDistrict[$distName] = [];
            }
            if (! isset($roadByDistrict[$distName][$rLabel])) {
                $roadByDistrict[$distName][$rLabel] = 0;
            }
            $roadByDistrict[$distName][$rLabel]++;
        }

        return [
            'water' => [
                'totals' => $waterCounts,
                'by_district' => $waterByDistrict,
            ],
            'road' => [
                'totals' => array_filter($roadCounts, fn ($c) => $c > 0),
                'by_district' => $roadByDistrict,
            ],
        ];
    }

    /**
     * Landholding size distribution bands:
     * - Marginal (< 5 Kanals / < 0.625 Acres)
     * - Small (5 – 12.5 Kanals / 0.625 – 1.56 Acres)
     * - Medium (12.5 – 25 Kanals / 1.56 – 3.125 Acres)
     * - Large (> 25 Kanals / > 3.125 Acres)
     */
    public function getLandholdingBands(array $filters = [], ?User $user = null): array
    {
        $apps = $this->getFilteredQuery($filters, $user)
            ->with(['fieldValues.formField'])
            ->get();

        $bands = [
            'Marginal (< 5 Kanals)' => ['count' => 0, 'kanals' => 0.0, 'bags' => 0],
            'Small (5 – 12.5 Kanals)' => ['count' => 0, 'kanals' => 0.0, 'bags' => 0],
            'Medium (12.5 – 25 Kanals)' => ['count' => 0, 'kanals' => 0.0, 'bags' => 0],
            'Large (> 25 Kanals)' => ['count' => 0, 'kanals' => 0.0, 'bags' => 0],
            'Unspecified / Not Captured' => ['count' => 0, 'kanals' => 0.0, 'bags' => 0],
        ];

        foreach ($apps as $app) {
            $parsed = $this->extractAppAttributes($app);
            $kanals = $parsed['land_kanals'];
            $bags = $parsed['requested_bags'];

            if ($kanals === null || $kanals <= 0) {
                $bandKey = 'Unspecified / Not Captured';
            } elseif ($kanals < 5) {
                $bandKey = 'Marginal (< 5 Kanals)';
            } elseif ($kanals <= 12.5) {
                $bandKey = 'Small (5 – 12.5 Kanals)';
            } elseif ($kanals <= 25) {
                $bandKey = 'Medium (12.5 – 25 Kanals)';
            } else {
                $bandKey = 'Large (> 25 Kanals)';
            }

            $bands[$bandKey]['count']++;
            $bands[$bandKey]['kanals'] += ($kanals ?? 0);
            $bands[$bandKey]['bags'] += $bags;
        }

        $total = $apps->count();
        foreach ($bands as $k => $data) {
            $bands[$k]['percent'] = $total > 0 ? round(($data['count'] / $total) * 100, 1) : 0;
            $bands[$k]['acres'] = round($data['kanals'] / self::KANALS_PER_ACRE, 2);
        }

        return $bands;
    }

    /**
     * Beneficiary list with normalized attributes, PII masking, and pagination.
     */
    public function getBeneficiaryList(array $filters = [], ?User $user = null, int|string $perPage = 25, bool $maskPii = false): LengthAwarePaginator
    {
        $resolvedPerPage = 25;
        if ($perPage === 'all' || (is_numeric($perPage) && (int) $perPage === -1)) {
            $resolvedPerPage = 5000;
        } elseif (is_numeric($perPage) && (int) $perPage > 0) {
            $resolvedPerPage = min(5000, (int) $perPage);
        }

        $paginator = $this->getFilteredQuery($filters, $user)
            ->with(['applicant', 'district', 'statusLogs.user', 'fieldValues.formField'])
            ->latest('applications.id')
            ->paginate($resolvedPerPage)
            ->withQueryString();

        $bagRate = $this->getConfiguredBagRate();

        $paginator->getCollection()->transform(function ($app) use ($maskPii, $bagRate) {
            $parsed = $this->extractAppAttributes($app);
            $applicant = $app->applicant;

            $rawCnic = $applicant?->cnic;
            $rawPhone = $applicant?->phone;

            $bagWeight = $this->getConfiguredBagWeight();
            $govShareFraction = $this->getConfiguredGovtSharePct() / 100;
            $farmerShareFraction = $this->getConfiguredFarmerSharePct() / 100;

            $latestLog = $app->statusLogs->first();

            $costShareEstimate = null;
            $distRate = $this->getRateForDistrict($app->district_id) ?? $bagRate;
            $distTransport = $this->getTransportCostForDistrict($app->district_id);

            if ($distRate !== null) {
                $totalVal = $parsed['requested_bags'] * $distRate;
                $costShareEstimate = [
                    'rate' => $distRate,
                    'base_rate' => $bagRate,
                    'transport_cost' => $distTransport,
                    'total' => $totalVal,
                    'farmer_share_50' => round($totalVal * $farmerShareFraction, 2),
                    'gov_share_50' => round($totalVal * $govShareFraction, 2),
                ];
            }

            return (object) [
                'id' => $app->id,
                'application_no' => $app->application_no,
                'status' => $app->status,
                'status_label' => $app->statusLabel(),
                'submitted_at' => $app->submitted_at ?? $app->created_at,
                'applicant_name' => $applicant?->full_name ?? '—',
                'father_name' => $applicant?->father_name ?? '—',
                'cnic' => $maskPii ? Cnic::mask($rawCnic) : Cnic::dashed($rawCnic),
                'raw_cnic' => $rawCnic,
                'phone' => $maskPii ? Cnic::maskPhone($rawPhone) : ($rawPhone ?? '—'),
                'raw_phone' => $rawPhone,
                'district_name' => $app->district?->name ?? '—',
                'division' => self::divisionForDistrict($app->district?->name),
                'tehsil' => $applicant?->tehsil ?: '—',
                'union_council' => $applicant?->union_council ?: '—',
                'village' => $applicant?->village ?: '—',
                'land_location' => $applicant?->land_location ?: '—',
                'land_kanals' => $parsed['land_kanals'],
                'land_acres' => $parsed['land_acres'],
                'cultivated_kanals_last_year' => $parsed['cultivated_kanals'],
                'cultivated_display' => $parsed['cultivated_kanals'] !== null ? $parsed['cultivated_kanals'] . ' Kanals' : 'Not yet captured',
                'requested_bags' => $parsed['requested_bags'],
                'approved_bags' => in_array($app->status, ['directorate_approved', 'disbursed'], true) ? $parsed['requested_bags'] : 0,
                'disbursed_bags' => $app->status === 'disbursed' ? $parsed['requested_bags'] : 0,
                'weight_kg' => $parsed['requested_bags'] * $bagWeight,
                'weight_mt' => round(($parsed['requested_bags'] * $bagWeight) / 1000, 2),
                'rate_per_bag' => $distRate,
                'transport_cost' => $distTransport,
                'water_available' => $parsed['water_available'] ?? 'Not yet captured',
                'road_access' => $parsed['road_access'] ?? 'Not yet captured',
                'survey_status' => (in_array($app->status, ['district_verified', 'directorate_approved', 'disbursed'], true) || ! empty($app->verification_note)) ? 'Verified / Inspected' : 'Pending Verification',
                'reviewing_officer' => $latestLog?->user?->name ?? '—',
                'verification_note' => $app->verification_note ?? $app->rejection_reason ?? '—',
                'cost_share' => $costShareEstimate,
            ];
        });

        return $paginator;
    }

    /**
     * GIS Farm Location data:
     * Returns coordinate points if present, or indicates absence clearly without guessing.
     */
    public function getGisFarmLocations(array $filters = [], ?User $user = null): array
    {
        $apps = $this->getFilteredQuery($filters, $user)
            ->with(['applicant', 'district', 'fieldValues.formField'])
            ->get();

        $points = [];
        $capturedCount = 0;
        $absentCount = 0;

        foreach ($apps as $app) {
            $parsed = $this->extractAppAttributes($app);
            if ($parsed['coordinates']) {
                $points[] = [
                    'application_no' => $app->application_no,
                    'applicant' => $app->applicant?->full_name,
                    'lat' => $parsed['coordinates']['lat'],
                    'lng' => $parsed['coordinates']['lng'],
                    'district' => $app->district?->name,
                    'village' => $app->applicant?->village,
                    'status' => $app->status,
                ];
                $capturedCount++;
            } else {
                $absentCount++;
            }
        }

        // Gilgit-Baltistan District centroids for reference mapping
        $districtCentroids = [
            'Gilgit' => ['lat' => 35.9221, 'lng' => 74.3087],
            'Hunza' => ['lat' => 36.3167, 'lng' => 74.6500],
            'Nagar' => ['lat' => 36.2500, 'lng' => 74.7167],
            'Skardu' => ['lat' => 35.2971, 'lng' => 75.6333],
            'Ghanche' => ['lat' => 35.1667, 'lng' => 76.3333],
            'Shigar' => ['lat' => 35.4167, 'lng' => 75.7333],
            'Kharmang' => ['lat' => 34.9333, 'lng' => 76.2167],
            'Astore' => ['lat' => 35.3667, 'lng' => 74.8500],
            'Diamer' => ['lat' => 35.4167, 'lng' => 74.1000],
            'Ghizer' => ['lat' => 36.1667, 'lng' => 73.7500],
        ];

        return [
            'verified_points' => $points,
            'captured_count' => $capturedCount,
            'absent_count' => $absentCount,
            'is_captured' => $capturedCount > 0,
            'status_note' => $capturedCount > 0
                ? "{$capturedCount} farm locations verified with GPS."
                : 'Not yet captured. Field GPS survey coordinates have not been recorded on submitted applications.',
            'district_centroids' => $districtCentroids,
        ];
    }

    /**
     * Extract and normalize persisted dynamic and core attributes for an Application.
     */
    public function extractAppAttributes(Application $app): array
    {
        $fieldValues = $app->relationLoaded('fieldValues')
            ? $app->fieldValues
            : $app->fieldValues()->with('formField')->get();

        $valuesByKey = [];
        foreach ($fieldValues as $fv) {
            $key = $fv->formField?->field_key ?: 'field_'.$fv->form_field_id;
            $valuesByKey[$key] = $fv->value;
            // Also store by form_field_id for direct lookup
            $valuesByKey['id_'.$fv->form_field_id] = $fv->value;
        }

        // Available Land in Kanals (Form Field ID: 8 or key 'land_kanals')
        $rawKanals = $valuesByKey['land_kanals'] ?? $valuesByKey['id_8'] ?? null;
        $landKanals = null;
        if ($rawKanals !== null && is_numeric(trim($rawKanals))) {
            $landKanals = (float) trim($rawKanals);
        }
        $landAcres = $landKanals !== null ? round($landKanals / self::KANALS_PER_ACRE, 2) : null;

        // Wheat Cultivated Land Last Year
        $rawCultivated = $valuesByKey['wheat_cultivated_area']
            ?? $valuesByKey['cultivated_land']
            ?? $valuesByKey['wheat_area_last_year']
            ?? null;
        $cultivatedKanals = null;
        if ($rawCultivated !== null && is_numeric(trim($rawCultivated))) {
            $cultivatedKanals = (float) trim($rawCultivated);
        }

        // Seed Bags Required (50kg each)
        // If explicitly recorded, use it; otherwise derive from standard agronomic seed rate:
        // 1 bag (50kg) per 4 kanals of available land (0.25 bag/kanal).
        $rawBags = $valuesByKey['bags_required']
            ?? $valuesByKey['seed_bags']
            ?? $valuesByKey['seed_bags_required']
            ?? $valuesByKey['wheat_seed_bags']
            ?? null;
        if ($rawBags !== null && is_numeric(trim($rawBags)) && (int) $rawBags > 0) {
            $requestedBags = (int) $rawBags;
        } elseif ($landKanals !== null && $landKanals > 0) {
            $requestedBags = (int) max(1, ceil($landKanals / $this->getConfiguredKanalsPerBag()));
        } else {
            $requestedBags = 0;
        }

        // Road Access (Form Field ID: 10 or key 'road_access')
        $roadAccess = $valuesByKey['road_access'] ?? $valuesByKey['id_10'] ?? null;

        // Water Available (Form Field ID: 11 or key 'water_available')
        $waterAvailable = $valuesByKey['water_available'] ?? $valuesByKey['id_11'] ?? null;

        // Tunnels (Form Field ID: 9 or key 'existing_tunnels')
        $existingTunnels = $valuesByKey['existing_tunnels'] ?? $valuesByKey['id_9'] ?? null;

        // Coordinates check
        $coordinates = null;
        $rawGps = $valuesByKey['gps_coordinates'] ?? $valuesByKey['coordinates'] ?? null;
        if ($rawGps && is_string($rawGps) && str_contains($rawGps, ',')) {
            $parts = explode(',', $rawGps);
            if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                $lat = (float) trim($parts[0]);
                $lng = (float) trim($parts[1]);
                if ($lat >= 30 && $lat <= 40 && $lng >= 70 && $lng <= 80) {
                    $coordinates = ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        return [
            'land_kanals' => $landKanals,
            'land_acres' => $landAcres,
            'cultivated_kanals' => $cultivatedKanals,
            'requested_bags' => $requestedBags,
            'road_access' => $roadAccess,
            'water_available' => $waterAvailable,
            'existing_tunnels' => $existingTunnels,
            'coordinates' => $coordinates,
            'village' => $app->applicant?->village,
        ];
    }

    /**
     * Complete Data Dictionary mapping form questions to database storage keys and KPI formulas.
     */
    public function getDataDictionary(): array
    {
        return [
            [
                'form_question' => 'Full Name',
                'field_key' => 'full_name',
                'field_id' => 1,
                'target_table' => 'applicants',
                'target_column' => 'full_name',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Trimmed string; title cased',
                'kpi_usage' => 'Beneficiary registry and export listing',
            ],
            [
                'form_question' => 'Father / Husband Name',
                'field_key' => 'father_name',
                'field_id' => 2,
                'target_table' => 'applicants',
                'target_column' => 'father_name',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Trimmed string',
                'kpi_usage' => 'Identity verification and beneficiary list',
            ],
            [
                'form_question' => 'CNIC Number',
                'field_key' => 'cnic',
                'field_id' => 3,
                'target_table' => 'applicants',
                'target_column' => 'cnic',
                'data_type' => 'VARCHAR(15)',
                'storage_type' => 'Core Applicant Attribute (Verified via API)',
                'normalization' => '13 numeric digits; dashed format 71501-XXXXXXX-X; PII masked in exports as 71501-*****12-3',
                'kpi_usage' => 'Unique beneficiary count; duplicate prevention',
            ],
            [
                'form_question' => 'Cell Number',
                'field_key' => 'phone',
                'field_id' => 4,
                'target_table' => 'applicants',
                'target_column' => 'phone',
                'data_type' => 'VARCHAR(20)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Numeric clean; PII masked as 0300-***4567 in reports',
                'kpi_usage' => 'Beneficiary communication and audit trail',
            ],
            [
                'form_question' => 'District',
                'field_key' => 'district_id',
                'field_id' => 5,
                'target_table' => 'applications / applicants',
                'target_column' => 'district_id',
                'data_type' => 'BIGINT UNSIGNED',
                'storage_type' => 'Foreign Key -> districts.id',
                'normalization' => 'Maps to 10 GB districts and 3 administrative divisions (Gilgit, Baltistan, Diamer)',
                'kpi_usage' => 'District-wise demand-supply gap; quotas; regional rollups',
            ],
            [
                'form_question' => 'Tehsil',
                'field_key' => 'tehsil',
                'field_id' => 6,
                'target_table' => 'applicants',
                'target_column' => 'tehsil',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Alphabetically sorted cascading tehsil selection',
                'kpi_usage' => 'Tehsil-level aggregation and filtering',
            ],
            [
                'form_question' => 'Union Council',
                'field_key' => 'union_council',
                'field_id' => 274,
                'target_table' => 'applicants',
                'target_column' => 'union_council',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Cascading selection linked to tehsil',
                'kpi_usage' => 'Union council distribution and saturation metrics',
            ],
            [
                'form_question' => 'Village',
                'field_key' => 'village',
                'field_id' => 296,
                'target_table' => 'applicants',
                'target_column' => 'village',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Trimmed string; grouped uniquely',
                'kpi_usage' => 'Village coverage count and average applicants per village',
            ],
            [
                'form_question' => 'Land / Farm Location',
                'field_key' => 'land_location',
                'field_id' => 7,
                'target_table' => 'applicants',
                'target_column' => 'land_location',
                'data_type' => 'TEXT',
                'storage_type' => 'Core Applicant Attribute',
                'normalization' => 'Physical description of the farm/field site',
                'kpi_usage' => 'Field survey site inspection',
            ],
            [
                'form_question' => 'Land Available (Kanals)',
                'field_key' => 'land_kanals',
                'field_id' => 8,
                'target_table' => 'application_field_values',
                'target_column' => 'value',
                'data_type' => 'DECIMAL / FLOAT',
                'storage_type' => 'EAV (application_field_values linked to form_field_id: 8)',
                'normalization' => 'Cast to numeric float >= 0. Formula: Acres = Kanals / 8',
                'kpi_usage' => 'Total Available Land (Kanals & Acres); Landholding Size Bands',
            ],
            [
                'form_question' => 'Existing Number of Tunnels',
                'field_key' => 'existing_tunnels',
                'field_id' => 9,
                'target_table' => 'application_field_values',
                'target_column' => 'value',
                'data_type' => 'INTEGER',
                'storage_type' => 'EAV (application_field_values linked to form_field_id: 9)',
                'normalization' => 'Cast to integer >= 0',
                'kpi_usage' => 'Farm infrastructure assessment',
            ],
            [
                'form_question' => 'Road Access to Land',
                'field_key' => 'road_access',
                'field_id' => 10,
                'target_table' => 'application_field_values',
                'target_column' => 'value',
                'data_type' => 'VARCHAR(100)',
                'storage_type' => 'EAV (options_json: ["Yes", "No"] / Main road / Link road / Pony track)',
                'normalization' => 'Categorized into Main Road, Link Road, Pony Track, Yes, No, or Not yet captured',
                'kpi_usage' => 'Road accessibility categorized bar chart & logistics feasibility',
            ],
            [
                'form_question' => 'Availability of Water',
                'field_key' => 'water_available',
                'field_id' => 11,
                'target_table' => 'application_field_values',
                'target_column' => 'value',
                'data_type' => 'VARCHAR(50)',
                'storage_type' => 'EAV (options_json: ["Yes", "No"])',
                'normalization' => 'Normalized to Yes, No, or Not yet captured',
                'kpi_usage' => 'Water availability donut chart & district breakdown',
            ],
            [
                'form_question' => 'Wheat Cultivated Area Last Year',
                'field_key' => 'wheat_cultivated_area',
                'field_id' => 'Dynamic / Survey',
                'target_table' => 'application_field_values',
                'target_column' => 'value',
                'data_type' => 'DECIMAL / FLOAT',
                'storage_type' => 'Dynamic EAV field (keys: wheat_cultivated_area, cultivated_land)',
                'normalization' => 'If absent: displays "Not yet captured" rather than fabricated 0',
                'kpi_usage' => 'Total Previous Cultivated Land & Available vs Cultivated Ratio',
            ],
            [
                'form_question' => 'Seed Bags Required (50kg each)',
                'field_key' => 'bags_required',
                'field_id' => 'Derived / Field',
                'target_table' => 'application_field_values / Agronomic standard',
                'target_column' => 'value',
                'data_type' => 'INTEGER',
                'storage_type' => 'Sourced from field if submitted, or standard rate: 1 bag (50kg) / 4 Kanals',
                'normalization' => '1 Bag = 50 kg; Metric Tons (MT) = (Bags * 50) / 1000',
                'kpi_usage' => 'Demand-supply gap, Weight equivalents (kg & MT), Cost-sharing (50:50)',
            ],
            [
                'form_question' => 'Copy of CNIC (Document)',
                'field_key' => 'cnic_document',
                'field_id' => 1,
                'target_table' => 'application_documents',
                'target_column' => 'file_path',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'File storage attachment (Mandatory)',
                'normalization' => 'Encrypted path; download via secure route',
                'kpi_usage' => 'Document verification audit',
            ],
            [
                'form_question' => 'Cost-Sharing Approved Bag Rate',
                'field_key' => 'wheat_seed_bag_rate',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'DECIMAL(10,2)',
                'storage_type' => 'Configured site setting (SiteSetting::getValue)',
                'normalization' => 'PKR per standard bag. If not configured: displays "Not yet configured / Not yet captured"',
                'kpi_usage' => 'Gov Share = Bags * Rate * (Gov % / 100); Farmer Share = Bags * Rate * (Farmer % / 100)',
            ],
            [
                'form_question' => 'Standard Bag Weight (kg)',
                'field_key' => 'wheat_seed_bag_weight_kg',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'DECIMAL(8,2)',
                'storage_type' => 'Configured site setting (Default: 50.00 kg)',
                'normalization' => 'Weight per bag in kilograms; used for Total Weight (kg) and Metric Tons (MT)',
                'kpi_usage' => 'Weight (kg) = Bags * Bag Weight; Metric Tons = Weight (kg) / 1000',
            ],
            [
                'form_question' => 'Government Cost-Share Percentage',
                'field_key' => 'wheat_seed_govt_share_pct',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'DECIMAL(5,2)',
                'storage_type' => 'Configured site setting (Default: 50%)',
                'normalization' => 'Percentage of seed cost borne by the Agriculture Department',
                'kpi_usage' => 'Gov Subsidy = Approved Bags * Rate * (Gov % / 100)',
            ],
            [
                'form_question' => 'Farmer Contribution Percentage',
                'field_key' => 'wheat_seed_farmer_share_pct',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'DECIMAL(5,2)',
                'storage_type' => 'Configured site setting (Default: 50%)',
                'normalization' => 'Percentage of seed cost contributed by the beneficiary farmer',
                'kpi_usage' => 'Farmer Contribution = Approved Bags * Rate * (Farmer % / 100)',
            ],
            [
                'form_question' => 'Baseline Agronomic Sowing Rate (Kanals/Bag)',
                'field_key' => 'wheat_seed_kanals_per_bag',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'DECIMAL(6,2)',
                'storage_type' => 'Configured site setting (Default: 4.00 kanals/bag = 12.5 kg/kanal)',
                'normalization' => 'Used to derive baseline seed requirement when applicant enters available land',
                'kpi_usage' => 'Derived Bags = ceil(Available Kanals / Kanals Per Bag)',
            ],
            [
                'form_question' => 'Certified Seed Variety & Season',
                'field_key' => 'wheat_seed_variety_name',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'VARCHAR(255)',
                'storage_type' => 'Configured site setting (e.g. Certified Seed Rabi 2025-26)',
                'normalization' => 'Official seed specification / certified variety descriptor',
                'kpi_usage' => 'Executive scheme context and procurement traceability',
            ],
            [
                'form_question' => 'District Transportation & Delivered Seed Cost',
                'field_key' => 'wheat_seed_district_rates',
                'field_id' => 'Configuration',
                'target_table' => 'site_settings',
                'target_column' => 'value',
                'data_type' => 'JSON',
                'storage_type' => 'Configured site setting (JSON map of district_id to transport_cost and delivered rate)',
                'normalization' => 'Delivered Bag Cost = Base Rate + District Transportation Carriage. Falls back to Base Rate if 0.',
                'kpi_usage' => 'District-wise demand-supply gap valuation, beneficiary cost share, and regional financial subsidy',
            ],
            [
                'form_question' => 'District Transport / Carriage Charge',
                'field_key' => 'transport_cost',
                'field_id' => 'Derived / Config',
                'target_table' => 'site_settings (wheat_seed_district_rates)',
                'target_column' => 'transport_cost',
                'data_type' => 'DECIMAL(8,2)',
                'storage_type' => 'Per-bag transport surcharge per district (PKR)',
                'normalization' => 'Carriage cost added to base rate to reflect logistical transit costs across GB districts',
                'kpi_usage' => 'Total Transport Subsidy = Approved Bags * Transport Charge per Bag',
            ],
        ];
    }
}

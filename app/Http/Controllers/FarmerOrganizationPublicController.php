<?php

namespace App\Http\Controllers;

use App\Models\FarmerOrganization;
use App\Support\GbGeoCoordinates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FarmerOrganizationPublicController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'division'  => $request->string('division')->trim()->toString(),
            'sheet'     => $request->string('sheet')->trim()->toString(),
            'district'  => $request->string('district')->trim()->toString(),
            'tehsil'    => $request->string('tehsil')->trim()->toString(),
            'uc'        => $request->string('uc')->trim()->toString(),
            'vo_status' => $request->string('vo_status')->trim()->toString(),
            'q'         => $request->string('q')->trim()->toString(),
        ];

        $meta = FarmerOrganization::toBase()->where('is_active', true)->selectRaw('COUNT(*) as cnt, MAX(updated_at) as max_upd')->first();
        $cacheKey = 'fo_public_analytics_' . ($meta->cnt ?? 0) . '_' . md5((string) ($meta->max_upd ?? '')) . '_' . md5(json_encode($filters));

        $analytics = Cache::remember($cacheKey, 300, function () use ($filters) {
            $allActiveRecords = FarmerOrganization::query()
                ->select(['id', 'district_id', 'sheet_code', 'division_name', 'district_name', 'sub_division', 'uc_mc', 'village_name', 'organization_name', 'is_vo_formed', 'focal_person_name', 'focal_person_phone', 'focal_person_raw', 'is_active'])
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            return GbGeoCoordinates::buildPageAnalyticsAndFilters($allActiveRecords, $filters);
        });

        $summaryRows     = $analytics['summaryRows'];
        $chartRows       = $analytics['chartRows'];
        $chartLevelLabel = $analytics['chartLevelLabel'];
        $totals          = $analytics['totals'];
        $mapClusters     = $analytics['mapClusters'];
        $tehsilOptions   = $analytics['tehsilOptions'];
        $ucOptions       = $analytics['ucOptions'];

        $activeDivision  = $filters['division'];
        $activeSheet     = $analytics['resolvedSheet'];
        $activeDistrict  = $analytics['resolvedDistrict'];
        $activeTehsil    = $filters['tehsil'];
        $activeUc        = $filters['uc'];
        $voStatus        = $filters['vo_status'];
        $search          = $filters['q'];

        $query = FarmerOrganization::query()->where('is_active', true);
        GbGeoCoordinates::applyFiltersToQuery(
            $query,
            array_merge($filters, ['sheet' => $activeSheet, 'district' => $activeDistrict]),
            $analytics['rawTehsilMap'],
            $analytics['rawUcMap']
        );

        $organizations = $query->orderBy('id')->paginate(50)->withQueryString();

        return view('public.organizations.index', compact(
            'summaryRows',
            'chartRows',
            'chartLevelLabel',
            'totals',
            'organizations',
            'mapClusters',
            'tehsilOptions',
            'ucOptions',
            'activeSheet',
            'activeDistrict',
            'activeDivision',
            'activeTehsil',
            'activeUc',
            'voStatus',
            'search'
        ));
    }
}

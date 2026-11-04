<?php

namespace App\Http\Controllers;

use App\Models\FarmerOrganization;
use App\Support\GbGeoCoordinates;
use Illuminate\Http\Request;
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

        $allActiveRecords = FarmerOrganization::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $analytics = GbGeoCoordinates::buildPageAnalyticsAndFilters($allActiveRecords, $filters);

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

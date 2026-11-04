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
        // 1. District Summary Statistics (ordered in official Excel Summary order)
        $orderedSheets = array_keys(FarmerOrganization::SHEET_DISTRICT_MAP);

        $rawSummary = FarmerOrganization::query()
            ->where('is_active', true)
            ->selectRaw('
                district_name,
                sheet_code,
                division_name,
                COUNT(*) as total_villages,
                SUM(CASE WHEN is_vo_formed = 1 THEN 1 ELSE 0 END) as vos_formed,
                SUM(CASE WHEN is_vo_formed = 0 THEN 1 ELSE 0 END) as no_vos_formed
            ')
            ->groupBy('district_name', 'sheet_code', 'division_name')
            ->get()
            ->keyBy('sheet_code');

        $summaryRows = [];
        $totals = [
            'total_villages' => 0,
            'vos_formed' => 0,
            'no_vos_formed' => 0,
        ];

        $sr = 1;
        foreach (FarmerOrganization::SHEET_DISTRICT_MAP as $code => $meta) {
            $row = $rawSummary->get($code);
            $vTotal = (int) ($row->total_villages ?? 0);
            $vFormed = (int) ($row->vos_formed ?? 0);
            $vNotFormed = (int) ($row->no_vos_formed ?? 0);
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

            $totals['total_villages'] += $vTotal;
            $totals['vos_formed'] += $vFormed;
            $totals['no_vos_formed'] += $vNotFormed;
        }

        $totals['coverage_pct'] = $totals['total_villages'] > 0
            ? round(($totals['vos_formed'] / $totals['total_villages']) * 100, 1)
            : 0;

        // 2. Filtered Village Organizations Directory Query
        $query = FarmerOrganization::query()->where('is_active', true);

        $activeSheet = $request->string('sheet')->trim()->toString();
        $activeDistrict = $request->string('district')->trim()->toString();
        $activeDivision = $request->string('division')->trim()->toString();
        $voStatus = $request->string('vo_status')->trim()->toString();
        $search = $request->string('q')->trim()->toString();

        if ($activeSheet !== '' && isset(FarmerOrganization::SHEET_DISTRICT_MAP[$activeSheet])) {
            $query->where('sheet_code', $activeSheet);
        } elseif ($activeDistrict !== '') {
            $query->where('district_name', $activeDistrict);
        }

        if ($activeDivision !== '') {
            $query->where('division_name', $activeDivision);
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

        $organizations = $query->orderBy('id')->paginate(50)->withQueryString();

        $allActiveRecords = FarmerOrganization::query()
            ->where('is_active', true)
            ->select(['district_name', 'sheet_code', 'division_name', 'sub_division', 'uc_mc', 'village_name', 'organization_name', 'is_vo_formed'])
            ->get();
        $mapClusters = GbGeoCoordinates::buildMapClusters($allActiveRecords);

        return view('public.organizations.index', compact(
            'summaryRows',
            'totals',
            'organizations',
            'mapClusters',
            'activeSheet',
            'activeDistrict',
            'activeDivision',
            'voStatus',
            'search'
        ));
    }
}


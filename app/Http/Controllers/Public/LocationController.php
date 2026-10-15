<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Return active districts sorted alphabetically.
     */
    public function districts(): JsonResponse
    {
        $districts = District::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($districts);
    }

    /**
     * Return tehsils belonging to the specified district, sorted alphabetically.
     */
    public function tehsils(Request $request, $district): JsonResponse
    {
        $districtModel = is_numeric($district)
            ? District::find($district)
            : District::where('code', $district)->orWhere('name', $district)->first();

        if (! $districtModel) {
            return response()->json([
                'message' => 'District not found.',
                'data' => [],
            ], 404);
        }

        $tehsils = Tehsil::query()
            ->where('district_id', $districtModel->id)
            ->orderBy('name')
            ->get(['id', 'district_id', 'name']);

        return response()->json($tehsils);
    }

    /**
     * Return union councils belonging to the specified tehsil, sorted alphabetically.
     */
    public function unionCouncils(Request $request, $tehsil): JsonResponse
    {
        $tehsilModel = is_numeric($tehsil)
            ? Tehsil::find($tehsil)
            : Tehsil::where('name', $tehsil)->first();

        if (! $tehsilModel) {
            return response()->json([
                'message' => 'Tehsil not found.',
                'data' => [],
            ], 404);
        }

        $unionCouncils = UnionCouncil::query()
            ->where('tehsil_id', $tehsilModel->id)
            ->orderBy('name')
            ->get(['id', 'tehsil_id', 'name']);

        return response()->json($unionCouncils);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\District;
use App\Models\FarmerOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmerOrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $rawSummary = FarmerOrganization::query()
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

        $query = FarmerOrganization::query();

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
        $districts = District::orderBy('name')->get();

        return view('admin.organizations.index', compact(
            'summaryRows',
            'totals',
            'organizations',
            'districts',
            'activeSheet',
            'activeDistrict',
            'activeDivision',
            'voStatus',
            'search'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can create organizations.');
        }

        $validated = $request->validate([
            'district_name' => ['required', 'string', 'max:100'],
            'division_name' => ['nullable', 'string', 'max:100'],
            'sub_division' => ['nullable', 'string', 'max:150'],
            'uc_mc' => ['nullable', 'string', 'max:150'],
            'village_name' => ['required', 'string', 'max:200'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'is_vo_formed' => ['nullable', 'boolean'],
            'focal_person_name' => ['nullable', 'string', 'max:150'],
            'focal_person_phone' => ['nullable', 'string', 'max:100'],
        ]);

        $district = District::where('name', $validated['district_name'])->first();
        $sheetCode = FarmerOrganization::resolveSheetCode($validated['district_name']);
        $divisionName = ! empty($validated['division_name'])
            ? $validated['division_name']
            : FarmerOrganization::resolveDivisionName($validated['district_name']);

        $orgName = trim((string) ($validated['organization_name'] ?? ''));
        $isFormed = $request->has('is_vo_formed')
            ? $request->boolean('is_vo_formed')
            : ($orgName !== '' && strtolower($orgName) !== 'not formed yet');

        $fpName = trim((string) ($validated['focal_person_name'] ?? ''));
        $fpPhone = trim((string) ($validated['focal_person_phone'] ?? ''));
        $fpRaw = trim($fpName . ' ' . $fpPhone);

        $org = FarmerOrganization::create([
            'district_id' => $district?->id,
            'sheet_code' => $sheetCode,
            'division_name' => $divisionName,
            'district_name' => $validated['district_name'],
            'sub_division' => $validated['sub_division'] ?: null,
            'uc_mc' => $validated['uc_mc'] ?: null,
            'village_name' => $validated['village_name'],
            'organization_name' => $isFormed && $orgName !== '' ? $orgName : null,
            'is_vo_formed' => $isFormed,
            'focal_person_name' => $fpName ?: null,
            'focal_person_phone' => $fpPhone ?: null,
            'focal_person_raw' => $fpRaw ?: null,
            'is_active' => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        AccessAuditLog::log('farmer_organization_created', FarmerOrganization::class, (string) $org->id, [
            'village_name' => $org->village_name,
            'organization_name' => $org->organization_name,
            'district_name' => $org->district_name,
        ]);

        return back()->with('success', "Village / Farmer Organization record for '{$org->village_name}' ({$org->district_name}) added successfully.");
    }

    public function update(Request $request, FarmerOrganization $organization): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can edit organizations.');
        }

        $validated = $request->validate([
            'district_name' => ['required', 'string', 'max:100'],
            'division_name' => ['nullable', 'string', 'max:100'],
            'sub_division' => ['nullable', 'string', 'max:150'],
            'uc_mc' => ['nullable', 'string', 'max:150'],
            'village_name' => ['required', 'string', 'max:200'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'is_vo_formed' => ['nullable', 'boolean'],
            'focal_person_name' => ['nullable', 'string', 'max:150'],
            'focal_person_phone' => ['nullable', 'string', 'max:100'],
        ]);

        $district = District::where('name', $validated['district_name'])->first();
        $sheetCode = FarmerOrganization::resolveSheetCode($validated['district_name']);
        $divisionName = ! empty($validated['division_name'])
            ? $validated['division_name']
            : FarmerOrganization::resolveDivisionName($validated['district_name']);

        $orgName = trim((string) ($validated['organization_name'] ?? ''));
        $isFormed = $request->has('is_vo_formed')
            ? $request->boolean('is_vo_formed')
            : ($orgName !== '' && strtolower($orgName) !== 'not formed yet');

        $fpName = trim((string) ($validated['focal_person_name'] ?? ''));
        $fpPhone = trim((string) ($validated['focal_person_phone'] ?? ''));
        $fpRaw = trim($fpName . ' ' . $fpPhone);

        $organization->update([
            'district_id' => $district?->id,
            'sheet_code' => $sheetCode,
            'division_name' => $divisionName,
            'district_name' => $validated['district_name'],
            'sub_division' => $validated['sub_division'] ?: null,
            'uc_mc' => $validated['uc_mc'] ?: null,
            'village_name' => $validated['village_name'],
            'organization_name' => $isFormed && $orgName !== '' ? $orgName : null,
            'is_vo_formed' => $isFormed,
            'focal_person_name' => $fpName ?: null,
            'focal_person_phone' => $fpPhone ?: null,
            'focal_person_raw' => $fpRaw ?: null,
            'updated_by' => auth()->id(),
        ]);

        AccessAuditLog::log('farmer_organization_updated', FarmerOrganization::class, (string) $organization->id, [
            'village_name' => $organization->village_name,
            'organization_name' => $organization->organization_name,
            'district_name' => $organization->district_name,
        ]);

        return back()->with('success', "Village / Farmer Organization record for '{$organization->village_name}' updated successfully.");
    }

    public function destroy(FarmerOrganization $organization): RedirectResponse
    {
        if (! auth()->user()?->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can delete organizations.');
        }

        $village = $organization->village_name;
        $district = $organization->district_name;

        AccessAuditLog::log('farmer_organization_deleted', FarmerOrganization::class, (string) $organization->id, [
            'village_name' => $village,
            'organization_name' => $organization->organization_name,
            'district_name' => $district,
        ]);

        $organization->delete();

        return back()->with('success', "Village / Farmer Organization record for '{$village}' ({$district}) deleted successfully.");
    }
}


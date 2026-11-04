<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\District;
use App\Models\FarmerOrganization;
use App\Support\GbGeoCoordinates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmerOrganizationController extends Controller
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

        $allRecords = FarmerOrganization::query()->orderBy('id')->get();
        $analytics = GbGeoCoordinates::buildPageAnalyticsAndFilters($allRecords, $filters);

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

        $query = FarmerOrganization::query();
        GbGeoCoordinates::applyFiltersToQuery(
            $query,
            array_merge($filters, ['sheet' => $activeSheet, 'district' => $activeDistrict]),
            $analytics['rawTehsilMap'],
            $analytics['rawUcMap']
        );

        $organizations = $query->orderBy('id')->paginate(50)->withQueryString();
        $districts = District::orderBy('name')->get();

        return view('admin.organizations.index', compact(
            'summaryRows',
            'chartRows',
            'chartLevelLabel',
            'totals',
            'organizations',
            'districts',
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


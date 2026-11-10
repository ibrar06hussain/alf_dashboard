<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessAuditLog;
use App\Models\CollectionCenter;
use App\Models\District;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DistrictController extends Controller
{
    public function index(): View
    {
        $districts = District::with([
            'tehsils' => fn ($q) => $q->withCount('unionCouncils')->with('unionCouncils')->orderBy('name'),
        ])
            ->withCount(['applications', 'tehsils'])
            ->orderBy('name')
            ->paginate(50);

        $allDistricts = District::with('tehsils')->orderBy('name')->get();

        $pageStats = [
            ['label' => 'Districts', 'value' => District::count(), 'icon' => 'fa-solid fa-map', 'tone' => 'green'],
            ['label' => 'Active', 'value' => District::where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'Tehsils', 'value' => Tehsil::count(), 'icon' => 'fa-solid fa-map-pin', 'tone' => 'amber'],
            ['label' => 'Union Councils', 'value' => UnionCouncil::count(), 'icon' => 'fa-solid fa-layer-group', 'tone' => 'blue'],
        ];

        return view('admin.districts.index', compact('districts', 'allDistricts', 'pageStats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:districts,code'],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        $district = District::create($data);

        AccessAuditLog::log('district_created', District::class, (string) $district->id, [
            'name' => $district->name,
            'code' => $district->code,
        ]);

        return redirect()->route('admin.districts.index')->with('success', 'District created.');
    }

    public function update(Request $request, District $district): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:districts,code,'.$district->id],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        $district->update($data);

        AccessAuditLog::log('district_updated', District::class, (string) $district->id, [
            'name' => $district->name,
            'code' => $district->code,
            'is_active' => $district->is_active,
        ]);

        return redirect()->route('admin.districts.index')->with('success', 'District updated.');
    }

    public function storeTehsil(Request $request, District $district): RedirectResponse
    {
        return $this->createTehsilForDistrict($request, $district);
    }

    public function storeTehsilGeneral(Request $request): RedirectResponse
    {
        $request->validate([
            'district_id' => ['required', 'exists:districts,id'],
        ]);

        $district = District::findOrFail($request->input('district_id'));

        return $this->createTehsilForDistrict($request, $district);
    }

    private function createTehsilForDistrict(Request $request, District $district): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        $request->merge(['name' => $name]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('tehsils', 'name')->where('district_id', $district->id),
            ],
        ], [
            'name.unique' => "Tehsil \"{$name}\" already exists in {$district->name}.",
        ]);

        $tehsil = $district->tehsils()->create([
            'name' => $data['name'],
        ]);

        AccessAuditLog::log('tehsil_created', Tehsil::class, (string) $tehsil->id, [
            'district_id' => $district->id,
            'district_name' => $district->name,
            'name' => $tehsil->name,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Tehsil \"{$tehsil->name}\" added to {$district->name}.")
            ->with('expanded_district_id', $district->id)
            ->with('expanded_tehsil_id', $tehsil->id);
    }

    public function updateTehsil(Request $request, Tehsil $tehsil): RedirectResponse
    {
        $targetDistrictId = (int) $request->input('district_id', $tehsil->district_id);
        $name = trim((string) $request->input('name'));
        $request->merge(['name' => $name]);

        $data = $request->validate([
            'district_id' => ['sometimes', 'required', 'exists:districts,id'],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('tehsils', 'name')
                    ->where('district_id', $targetDistrictId)
                    ->ignore($tehsil->id),
            ],
        ], [
            'name.unique' => "Tehsil \"{$name}\" already exists in the selected district.",
        ]);

        $oldName = $tehsil->name;
        $tehsil->update([
            'district_id' => $targetDistrictId,
            'name' => $data['name'],
        ]);

        AccessAuditLog::log('tehsil_updated', Tehsil::class, (string) $tehsil->id, [
            'district_id' => $tehsil->district_id,
            'old_name' => $oldName,
            'new_name' => $tehsil->name,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Tehsil \"{$tehsil->name}\" updated.")
            ->with('expanded_district_id', $tehsil->district_id)
            ->with('expanded_tehsil_id', $tehsil->id);
    }

    public function destroyTehsil(Tehsil $tehsil): RedirectResponse
    {
        $districtId = $tehsil->district_id;
        $tehsilName = $tehsil->name;
        $tehsilId = $tehsil->id;
        $ucCount = $tehsil->unionCouncils()->count();

        DB::transaction(function () use ($tehsil) {
            if (\Illuminate\Support\Facades\Schema::hasTable('collection_centers')) {
                CollectionCenter::where('tehsil_id', $tehsil->id)->update(['tehsil_id' => null]);
            }
            $tehsil->unionCouncils()->delete();
            $tehsil->delete();
        });

        AccessAuditLog::log('tehsil_deleted', Tehsil::class, (string) $tehsilId, [
            'district_id' => $districtId,
            'name' => $tehsilName,
            'deleted_union_councils' => $ucCount,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Tehsil \"{$tehsilName}\" deleted" . ($ucCount > 0 ? " (along with {$ucCount} union council(s))." : '.'))
            ->with('expanded_district_id', $districtId);
    }

    public function storeUnionCouncil(Request $request, Tehsil $tehsil): RedirectResponse
    {
        return $this->createUnionCouncilForTehsil($request, $tehsil);
    }

    public function storeUnionCouncilGeneral(Request $request): RedirectResponse
    {
        $request->validate([
            'tehsil_id' => ['required', 'exists:tehsils,id'],
        ]);

        $tehsil = Tehsil::findOrFail($request->input('tehsil_id'));

        return $this->createUnionCouncilForTehsil($request, $tehsil);
    }

    private function createUnionCouncilForTehsil(Request $request, Tehsil $tehsil): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        $request->merge(['name' => $name]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('union_councils', 'name')->where('tehsil_id', $tehsil->id),
            ],
        ], [
            'name.unique' => "Union Council \"{$name}\" already exists in Tehsil {$tehsil->name}.",
        ]);

        $uc = $tehsil->unionCouncils()->create([
            'name' => $data['name'],
        ]);

        AccessAuditLog::log('union_council_created', UnionCouncil::class, (string) $uc->id, [
            'district_id' => $tehsil->district_id,
            'tehsil_id' => $tehsil->id,
            'tehsil_name' => $tehsil->name,
            'name' => $uc->name,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Union Council \"{$uc->name}\" added to Tehsil {$tehsil->name}.")
            ->with('expanded_district_id', $tehsil->district_id)
            ->with('expanded_tehsil_id', $tehsil->id);
    }

    public function updateUnionCouncil(Request $request, UnionCouncil $unionCouncil): RedirectResponse
    {
        $targetTehsilId = (int) $request->input('tehsil_id', $unionCouncil->tehsil_id);
        $name = trim((string) $request->input('name'));
        $request->merge(['name' => $name]);

        $data = $request->validate([
            'tehsil_id' => ['sometimes', 'required', 'exists:tehsils,id'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('union_councils', 'name')
                    ->where('tehsil_id', $targetTehsilId)
                    ->ignore($unionCouncil->id),
            ],
        ], [
            'name.unique' => "Union Council \"{$name}\" already exists in the selected tehsil.",
        ]);

        $oldName = $unionCouncil->name;
        $unionCouncil->update([
            'tehsil_id' => $targetTehsilId,
            'name' => $data['name'],
        ]);

        $tehsil = Tehsil::find($unionCouncil->tehsil_id);

        AccessAuditLog::log('union_council_updated', UnionCouncil::class, (string) $unionCouncil->id, [
            'district_id' => $tehsil?->district_id,
            'tehsil_id' => $unionCouncil->tehsil_id,
            'old_name' => $oldName,
            'new_name' => $unionCouncil->name,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Union Council \"{$unionCouncil->name}\" updated.")
            ->with('expanded_district_id', $tehsil?->district_id)
            ->with('expanded_tehsil_id', $unionCouncil->tehsil_id);
    }

    public function destroyUnionCouncil(UnionCouncil $unionCouncil): RedirectResponse
    {
        $tehsil = $unionCouncil->tehsil;
        $ucId = $unionCouncil->id;
        $ucName = $unionCouncil->name;

        $unionCouncil->delete();

        AccessAuditLog::log('union_council_deleted', UnionCouncil::class, (string) $ucId, [
            'district_id' => $tehsil?->district_id,
            'tehsil_id' => $tehsil?->id,
            'name' => $ucName,
        ]);

        return redirect()->route('admin.districts.index')
            ->with('success', "Union Council \"{$ucName}\" deleted.")
            ->with('expanded_district_id', $tehsil?->district_id)
            ->with('expanded_tehsil_id', $tehsil?->id);
    }
}

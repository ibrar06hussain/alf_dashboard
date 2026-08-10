<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistrictController extends Controller
{
    public function index(): View
    {
        $districts = District::withCount('applications')->orderBy('name')->paginate(50);

        $pageStats = [
            ['label' => 'Districts', 'value' => District::count(), 'icon' => 'fa-solid fa-map', 'tone' => 'green'],
            ['label' => 'Active', 'value' => District::where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'Inactive', 'value' => District::where('is_active', false)->count(), 'icon' => 'fa-solid fa-ban', 'tone' => 'slate'],
            ['label' => 'With applications', 'value' => District::has('applications')->count(), 'icon' => 'fa-solid fa-folder-open', 'tone' => 'blue'],
        ];

        return view('admin.districts.index', compact('districts', 'pageStats'));
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

        District::create($data);

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

        return redirect()->route('admin.districts.index')->with('success', 'District updated.');
    }
}

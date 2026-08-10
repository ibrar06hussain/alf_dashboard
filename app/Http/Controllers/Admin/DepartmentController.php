<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount(['schemes', 'stats'])
            ->orderBy('display_order')
            ->paginate(20);

        $pageStats = [
            ['label' => 'Departments', 'value' => Department::count(), 'icon' => 'fa-solid fa-landmark', 'tone' => 'green'],
            ['label' => 'Active', 'value' => Department::where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'Linked schemes', 'value' => \App\Models\Scheme::count(), 'icon' => 'fa-solid fa-file-lines', 'tone' => 'blue'],
            ['label' => 'Without schemes', 'value' => Department::doesntHave('schemes')->count(), 'icon' => 'fa-solid fa-circle-exclamation', 'tone' => 'amber'],
        ];

        return view('admin.departments.index', compact('departments', 'pageStats'));
    }

    public function create(): View
    {
        return view('admin.departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('departments', 'public');
        }

        Department::create($data);

        return redirect()->route('admin.departments.index')->with('success', 'Department created.');
    }

    public function show(Department $department): View
    {
        $department->load(['stats', 'schemes']);

        return view('admin.departments.show', compact('department'));
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validated($request, $department->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('cover_image')) {
            if ($department->cover_image) {
                Storage::disk('public')->delete($department->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('departments', 'public');
        }

        $department->update($data);

        return redirect()->route('admin.departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->cover_image) {
            Storage::disk('public')->delete($department->cover_image);
        }
        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'Department deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ur' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:departments,slug,'.($ignoreId ?? 'NULL')],
            'vision' => ['nullable', 'string'],
            'vision_ur' => ['nullable', 'string'],
            'mission' => ['nullable', 'string'],
            'mission_ur' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'objectives_ur' => ['nullable', 'string'],
            'leadership_info' => ['nullable', 'string'],
            'leadership_info_ur' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function show(string $slug): View
    {
        $department = Department::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'stats' => fn ($q) => $q->where('is_active', true)->orderBy('display_order'),
                'schemes' => fn ($q) => $q->where('status', 'active')->latest(),
            ])
            ->firstOrFail();

        return view('public.departments.show', compact('department'));
    }
}

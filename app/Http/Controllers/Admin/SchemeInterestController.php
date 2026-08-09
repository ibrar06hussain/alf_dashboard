<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Scheme;
use App\Models\SchemeInterest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchemeInterestController extends Controller
{
    public function index(Request $request): View
    {
        $query = SchemeInterest::query()
            ->with(['scheme.department', 'district'])
            ->latest();

        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('scheme', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($builder) use ($q) {
                $builder->where('full_name', 'like', "%{$q}%")
                    ->orWhere('cnic', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $schemes = Scheme::where('type', 'proposed')->orderBy('title')->get(['id', 'title']);
        $departments = Department::orderBy('display_order')->get();

        $pageStats = [
            ['label' => 'Total interests', 'value' => (clone $query)->count(), 'icon' => 'fa-solid fa-bell', 'tone' => 'amber'],
            ['label' => 'Schemes', 'value' => (int) (clone $query)->reorder()->toBase()->distinct()->count('scheme_id'), 'icon' => 'fa-solid fa-flag', 'tone' => 'green'],
            ['label' => 'Districts', 'value' => (int) (clone $query)->reorder()->whereNotNull('district_id')->toBase()->distinct()->count('district_id'), 'icon' => 'fa-solid fa-location-dot', 'tone' => 'blue'],
            ['label' => 'This week', 'value' => (clone $query)->where('created_at', '>=', now()->subDays(7))->count(), 'icon' => 'fa-solid fa-calendar-week', 'tone' => 'teal'],
        ];

        $interests = $query->paginate(25)->withQueryString();

        return view('admin.interests.index', compact('interests', 'schemes', 'departments', 'pageStats'));
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CarouselSlide;
use App\Models\Department;
use App\Models\Scheme;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $carouselSlides = CarouselSlide::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->with(['stats' => fn ($q) => $q->where('is_active', true)])
            ->withCount([
                'schemes as active_schemes_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderBy('display_order')
            ->get();

        $featuredSchemes = Scheme::query()
            ->with('department')
            ->where('status', 'active')
            ->where('type', 'running')
            ->where('is_featured', true)
            ->latest()
            ->take(6)
            ->get();

        $proposedSchemes = Scheme::query()
            ->with('department')
            ->withCount('interests')
            ->where('status', 'active')
            ->where('type', 'proposed')
            ->where('is_featured', true)
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->take(12)
            ->get();

        $stats = [
            'departments' => $departments->count(),
            'schemes' => Scheme::where('status', 'active')->count(),
            'running' => Scheme::where('status', 'active')->where('type', 'running')->count(),
            'proposed' => Scheme::where('status', 'active')->where('type', 'proposed')->count(),
            'applications' => Application::count(),
        ];

        return view('public.home', compact(
            'carouselSlides',
            'departments',
            'featuredSchemes',
            'proposedSchemes',
            'stats'
        ));
    }
}

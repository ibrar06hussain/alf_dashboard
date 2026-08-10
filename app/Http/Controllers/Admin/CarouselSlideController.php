<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarouselSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CarouselSlideController extends Controller
{
    public function index(): View
    {
        $slides = CarouselSlide::orderBy('display_order')->orderBy('id')->paginate(20);

        $pageStats = [
            ['label' => 'Slides', 'value' => CarouselSlide::count(), 'icon' => 'fa-solid fa-images', 'tone' => 'green'],
            ['label' => 'Active', 'value' => CarouselSlide::where('is_active', true)->count(), 'icon' => 'fa-solid fa-circle-check', 'tone' => 'teal'],
            ['label' => 'Inactive', 'value' => CarouselSlide::where('is_active', false)->count(), 'icon' => 'fa-solid fa-ban', 'tone' => 'slate'],
            ['label' => 'With image', 'value' => CarouselSlide::whereNotNull('image')->count(), 'icon' => 'fa-solid fa-image', 'tone' => 'blue'],
        ];

        return view('admin.carousel.index', compact('slides', 'pageStats'));
    }

    public function create(): View
    {
        return view('admin.carousel.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('carousel', 'public');
        }

        CarouselSlide::create($data);

        return redirect()->route('admin.carousel.index')->with('success', 'Carousel slide created.');
    }

    public function edit(CarouselSlide $carousel): View
    {
        return view('admin.carousel.edit', ['slide' => $carousel]);
    }

    public function update(Request $request, CarouselSlide $carousel): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        if ($request->hasFile('image')) {
            if ($carousel->image && ! str_starts_with($carousel->image, 'frontend/')) {
                Storage::disk('public')->delete($carousel->image);
            }
            $data['image'] = $request->file('image')->store('carousel', 'public');
        }

        $carousel->update($data);

        return redirect()->route('admin.carousel.index')->with('success', 'Carousel slide updated.');
    }

    public function destroy(CarouselSlide $carousel): RedirectResponse
    {
        if ($carousel->image && ! str_starts_with($carousel->image, 'frontend/')) {
            Storage::disk('public')->delete($carousel->image);
        }
        $carousel->delete();

        return redirect()->route('admin.carousel.index')->with('success', 'Carousel slide deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ur' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'subtitle_ur' => ['nullable', 'string', 'max:2000'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'button_label_ur' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'button2_label' => ['nullable', 'string', 'max:100'],
            'button2_label_ur' => ['nullable', 'string', 'max:100'],
            'button2_url' => ['nullable', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);
    }
}

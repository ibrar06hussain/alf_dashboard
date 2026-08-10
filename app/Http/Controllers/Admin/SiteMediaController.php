<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SiteMediaController extends Controller
{
    public function edit(): View
    {
        $this->ensureRows();

        $settings = SiteSetting::query()
            ->where('group', 'media')
            ->orderBy('id')
            ->get()
            ->keyBy('key');

        $pageStats = [
            ['label' => 'Media slots', 'value' => $settings->count(), 'icon' => 'fa-solid fa-photo-film', 'tone' => 'green'],
            ['label' => 'Custom uploads', 'value' => $settings->filter(fn ($s) => $s->value && ! str_starts_with((string) $s->value, 'frontend/'))->count(), 'icon' => 'fa-solid fa-cloud-arrow-up', 'tone' => 'blue'],
            ['label' => 'Using defaults', 'value' => $settings->filter(fn ($s) => ! $s->value)->count(), 'icon' => 'fa-solid fa-rotate-left', 'tone' => 'amber'],
            ['label' => 'Active covers', 'value' => $settings->filter(fn ($s) => filled($s->value) || filled($s->fallback))->count(), 'icon' => 'fa-solid fa-image', 'tone' => 'teal'],
        ];

        return view('admin.media.edit', compact('settings', 'pageStats'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureRows();

        $keys = collect(SiteSetting::mediaDefinitions())->pluck('key');

        foreach ($keys as $key) {
            $setting = SiteSetting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            if ($request->boolean('clear_'.$key)) {
                if ($setting->value && ! str_starts_with($setting->value, 'frontend/')) {
                    Storage::disk('public')->delete($setting->value);
                }
                $setting->update(['value' => null]);
                continue;
            }

            if ($request->hasFile('media_'.$key)) {
                $request->validate([
                    'media_'.$key => ['image', 'max:5120'],
                ]);

                if ($setting->value && ! str_starts_with($setting->value, 'frontend/')) {
                    Storage::disk('public')->delete($setting->value);
                }

                $path = $request->file('media_'.$key)->store('site-media', 'public');
                $setting->update(['value' => $path]);
            }
        }

        SiteSetting::flushCache();

        return back()->with('success', 'Site media updated.');
    }

    private function ensureRows(): void
    {
        foreach (SiteSetting::mediaDefinitions() as $def) {
            SiteSetting::firstOrCreate(
                ['key' => $def['key']],
                [
                    'label' => $def['label'],
                    'group' => 'media',
                    'fallback' => $def['fallback'],
                    'help_text' => $def['help_text'],
                    'value' => null,
                ]
            );
        }
    }
}

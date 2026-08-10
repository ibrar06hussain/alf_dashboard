<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteInfoController extends Controller
{
    public function edit(): View
    {
        $this->ensureRows();

        $settings = SiteSetting::query()
            ->where('group', 'content')
            ->get()
            ->keyBy('key');

        $definitions = collect(SiteSetting::contentDefinitions())->groupBy('section');

        $pageStats = [
            ['label' => 'Info fields', 'value' => $settings->count(), 'icon' => 'fa-solid fa-address-card', 'tone' => 'green'],
            ['label' => 'With English', 'value' => $settings->filter(fn ($s) => filled($s->value))->count(), 'icon' => 'fa-solid fa-language', 'tone' => 'blue'],
            ['label' => 'With Urdu', 'value' => $settings->filter(fn ($s) => filled($s->value_ur))->count(), 'icon' => 'fa-solid fa-earth-asia', 'tone' => 'teal'],
            ['label' => 'Using defaults', 'value' => $settings->filter(fn ($s) => ! filled($s->value) && ! filled($s->value_ur))->count(), 'icon' => 'fa-solid fa-rotate-left', 'tone' => 'amber'],
        ];

        return view('admin.site-info.edit', compact('settings', 'definitions', 'pageStats'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureRows();

        foreach (SiteSetting::contentDefinitions() as $def) {
            $setting = SiteSetting::where('key', $def['key'])->first();
            if (! $setting) {
                continue;
            }

            $en = trim((string) $request->input('content_'.$def['key'], ''));
            $ur = trim((string) $request->input('content_'.$def['key'].'_ur', ''));

            $setting->update([
                'value' => $en !== '' ? $en : null,
                'value_ur' => ($def['translatable'] ?? false) && $ur !== '' ? $ur : null,
            ]);
        }

        SiteSetting::flushCache();

        return back()->with('success', 'Site information updated.');
    }

    private function ensureRows(): void
    {
        foreach (SiteSetting::contentDefinitions() as $def) {
            SiteSetting::updateOrCreate(
                ['key' => $def['key']],
                [
                    'label' => $def['label'],
                    'group' => 'content',
                    'fallback' => $def['fallback'],
                    'fallback_ur' => $def['fallback_ur'] ?? null,
                    'help_text' => $def['help_text'],
                ]
            );
        }
    }
}

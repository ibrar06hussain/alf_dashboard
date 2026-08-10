<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'ur'], true)) {
            $locale = 'en';
        }

        $request->session()->put('locale', $locale);
        Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365));

        $target = $this->resolveRedirectTarget($request);

        return redirect()->to($target);
    }

    private function resolveRedirectTarget(Request $request): string
    {
        $candidates = array_filter([
            $request->query('redirect'),
            url()->previous(),
        ]);

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (str_contains($candidate, '/locale/')) {
                continue;
            }

            $host = parse_url($candidate, PHP_URL_HOST);
            if ($host && ! in_array($host, [$request->getHost(), 'localhost', '127.0.0.1'], true)) {
                continue;
            }

            return $candidate;
        }

        return route('home');
    }
}

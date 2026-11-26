<?php

namespace App\Providers;

use App\Services\Otp\MockSmsOtpSender;
use App\Services\Otp\SmsOtpSender;
use App\Services\SchemeAnalyticsService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsOtpSender::class, MockSmsOtpSender::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.admin', function ($view) {
            $dashboards = collect();
            try {
                if (auth()->check()) {
                    $service = app(SchemeAnalyticsService::class);
                    $wheatMis = app(\App\Services\WheatSeedMisService::class);
                    $dashboards = $service->visibleSchemesFor(auth()->user())->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->title,
                        'url' => $service->dashboardUrlFor($s),
                        'is_wheat' => $wheatMis->isWheatSeedScheme($s->id, $s->slug, $s->title),
                    ]);
                }
            } catch (\Throwable $e) {
                $dashboards = collect();
            }
            $view->with('officerSchemeDashboards', $dashboards);
        });
    }
}

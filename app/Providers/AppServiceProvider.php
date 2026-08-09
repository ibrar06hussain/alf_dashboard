<?php

namespace App\Providers;

use App\Services\Otp\MockSmsOtpSender;
use App\Services\Otp\SmsOtpSender;
use Illuminate\Pagination\Paginator;
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
    }
}

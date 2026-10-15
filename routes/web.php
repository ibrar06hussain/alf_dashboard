<?php

use App\Http\Controllers\Admin\ApplicationDocumentController;
use App\Http\Controllers\Admin\ApplicationReviewController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CarouselSlideController;
use App\Http\Controllers\Admin\CmDashboardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\DepartmentStatController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\Admin\SchemeController as AdminSchemeController;
use App\Http\Controllers\Admin\SchemeInterestController;
use App\Http\Controllers\Admin\SiteMediaController;
use App\Http\Controllers\Admin\SiteInfoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Public\ApplicationController;
use App\Http\Controllers\Public\DepartmentController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LocaleController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SchemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['en', 'ur'])
    ->name('locale.switch');

Route::get('/departments/{slug}', [DepartmentController::class, 'show'])->name('departments.show');

Route::get('/schemes', [SchemeController::class, 'index'])->name('schemes.index');
Route::get('/schemes/{slug}', [SchemeController::class, 'show'])->name('schemes.show');
Route::post('/schemes/{slug}/interest', [SchemeController::class, 'expressInterest'])->name('schemes.interest');

Route::get('/schemes/{slug}/apply', [ApplicationController::class, 'create'])->name('schemes.apply');
Route::post('/schemes/{slug}/apply', [ApplicationController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('schemes.apply.store');

Route::post('/otp/send', [ApplicationController::class, 'sendOtp'])
    ->middleware('throttle:5,1')
    ->name('otp.send');
Route::post('/otp/verify', [ApplicationController::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('otp.verify');

Route::get('/applications/success/{application_no}', [ApplicationController::class, 'success'])->name('applications.success');
Route::match(['get', 'post'], '/track', [ApplicationController::class, 'track'])->name('applications.track');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/locations/districts', [LocationController::class, 'districts'])->name('locations.districts');
Route::get('/districts/{district}/tehsils', [LocationController::class, 'tehsils'])->name('locations.tehsils');
Route::get('/tehsils/{tehsil}/union-councils', [LocationController::class, 'unionCouncils'])->name('locations.union-councils');
Route::get('/api/districts', [LocationController::class, 'districts']);
Route::get('/api/districts/{district}/tehsils', [LocationController::class, 'tehsils']);
Route::get('/api/tehsils/{tehsil}/union-councils', [LocationController::class, 'unionCouncils']);

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth', 'admin.active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Review workflow — all authenticated staff
        Route::get('applications', [ApplicationReviewController::class, 'index'])->name('applications.index');
        Route::get('applications/export', [ApplicationReviewController::class, 'export'])->name('applications.export');
        Route::get('applications/{application}', [ApplicationReviewController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/status', [ApplicationReviewController::class, 'updateStatus'])->name('applications.status');
        Route::get('applications/{application}/documents/{document}/download', [ApplicationDocumentController::class, 'download'])
            ->name('applications.documents.download');

        Route::get('interests', [SchemeInterestController::class, 'index'])->name('interests.index');

        // Directors + executives can manage schemes/forms/stats + CM analytics
        Route::middleware('role:Super Admin|Secretary|Director Agriculture|Director Livestock|Director Fisheries')->group(function () {
            Route::resource('schemes', AdminSchemeController::class);
            Route::get('forms', [FormBuilderController::class, 'index'])->name('form-builder.index');
            Route::get('application-forms', [FormBuilderController::class, 'index'])->name('application-forms.index');
            Route::get('schemes/{scheme}/form-builder', [FormBuilderController::class, 'edit'])->name('form-builder.edit');
            Route::put('schemes/{scheme}/form-builder', [FormBuilderController::class, 'update'])->name('form-builder.update');

            Route::get('stats', [DepartmentStatController::class, 'index'])->name('stats.index');
            Route::post('stats/refresh', [DepartmentStatController::class, 'refresh'])->name('stats.refresh');
            Route::get('stats/create', [DepartmentStatController::class, 'create'])->name('stats.create');
            Route::post('stats', [DepartmentStatController::class, 'store'])->name('stats.store');
            Route::get('stats/{stat}/edit', [DepartmentStatController::class, 'edit'])->name('stats.edit');
            Route::put('stats/{stat}', [DepartmentStatController::class, 'update'])->name('stats.update');
            Route::delete('stats/{stat}', [DepartmentStatController::class, 'destroy'])->name('stats.destroy');

            Route::get('cm-dashboard', [CmDashboardController::class, 'index'])->name('cm-dashboard.index');
            Route::get('cm-dashboard/analytics', [CmDashboardController::class, 'analytics'])->name('cm-dashboard.analytics');
            Route::get('cm-dashboard/export', [CmDashboardController::class, 'export'])->name('cm-dashboard.export');
        });

        // Super Admin / Secretary only
        Route::middleware('role:Super Admin|Secretary')->group(function () {
            Route::resource('departments', AdminDepartmentController::class);
            Route::get('districts', [DistrictController::class, 'index'])->name('districts.index');
            Route::post('districts', [DistrictController::class, 'store'])->name('districts.store');
            Route::put('districts/{district}', [DistrictController::class, 'update'])->name('districts.update');

            Route::resource('carousel', CarouselSlideController::class)->except(['show']);
            Route::get('media', [SiteMediaController::class, 'edit'])->name('media.edit');
            Route::put('media', [SiteMediaController::class, 'update'])->name('media.update');
            Route::get('site-info', [SiteInfoController::class, 'edit'])->name('site-info.edit');
            Route::put('site-info', [SiteInfoController::class, 'update'])->name('site-info.update');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        });
    });
});

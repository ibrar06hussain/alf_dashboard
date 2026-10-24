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
use App\Http\Controllers\Admin\StockManagementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WheatSeedMisController;
use App\Http\Controllers\Public\ApplicationController;
use App\Http\Controllers\Public\DepartmentController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LocaleController;
use App\Http\Controllers\Public\LocationController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SchemeController;
use App\Http\Controllers\Web\CnicVerificationController;
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

Route::post('/cnic/verify', [CnicVerificationController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('cnic.verify');
Route::post('/api/cnic/verify', [CnicVerificationController::class, 'verify']);

Route::get('/applications/success/{application_no}', [ApplicationController::class, 'success'])->name('applications.success');
Route::match(['get', 'post'], '/track', [ApplicationController::class, 'track'])->name('applications.track');
Route::get('/applications/{application_no}/challan', [ApplicationController::class, 'downloadChallan'])->name('applications.challan.download');
Route::post('/applications/{application_no}/payment-proof', [ApplicationController::class, 'uploadPaymentProof'])->name('applications.payment-proof.upload');
Route::post('/applications/{application_no}/correction', [ApplicationController::class, 'submitCorrection'])->name('applications.correction.submit');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');

// Fallback storage route for shared hosting environments (Hostinger / cPanel)
// Ensures uploaded images/documents are served even if Apache symlink fails or returns 403
Route::get('/storage/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(403);
    }
    $fullPath = storage_path('app/public/'.$path);
    if (! file_exists($fullPath) || is_dir($fullPath)) {
        abort(404);
    }
    return response()->file($fullPath, [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('storage.local');
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
        Route::get('/fix-storage-link', function () {
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
                return response()->json([
                    'status' => 'success',
                    'message' => 'The storage symlink has been created successfully!',
                    'output' => \Illuminate\Support\Facades\Artisan::output(),
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 500);
            }
        })->name('fix-storage-link');

        // Review workflow — all authenticated staff
        Route::get('applications', [ApplicationReviewController::class, 'index'])->name('applications.index');
        Route::get('applications/export', [ApplicationReviewController::class, 'export'])->name('applications.export');
        Route::get('applications/{application}', [ApplicationReviewController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/status', [ApplicationReviewController::class, 'updateStatus'])->name('applications.status');
        Route::post('applications/{application}/assign-field-officer', [ApplicationReviewController::class, 'assignFieldOfficer'])->name('applications.assign-field-officer');
        Route::post('applications/{application}/assign-survey', [ApplicationReviewController::class, 'assignSurvey'])->name('applications.assign-survey');
        Route::post('applications/surveys/{survey}/schedule', [ApplicationReviewController::class, 'scheduleSurveyVisit'])->name('applications.surveys.schedule');
        Route::post('applications/surveys/{survey}/complete', [ApplicationReviewController::class, 'completeSurveyVisit'])->name('applications.surveys.complete');
        Route::post('applications/surveys/{survey}/findings', [ApplicationReviewController::class, 'submitSurveyFindings'])->name('applications.surveys.findings');
        Route::post('applications/{application}/correction-request', [ApplicationReviewController::class, 'requestCorrection'])->name('applications.correction-request');
        Route::post('applications/{application}/verify-payment', [ApplicationReviewController::class, 'verifyPayment'])->name('applications.verify-payment');
        Route::post('applications/{application}/allocate-stock', [ApplicationReviewController::class, 'allocateStock'])->name('applications.allocate-stock');
        Route::post('applications/{application}/reactivate', [ApplicationReviewController::class, 'reactivateDormant'])->name('applications.reactivate');
        Route::post('applications/{application}/assign-dd', [ApplicationReviewController::class, 'assignDeputyDirector'])->name('applications.assign-dd');
        Route::post('applications/assignments/{assignment}/report', [ApplicationReviewController::class, 'submitTaskReport'])->name('applications.assignments.report');
        Route::get('applications/{application}/documents/{document}/download', [ApplicationDocumentController::class, 'download'])
            ->name('applications.documents.download');

        Route::get('interests', [SchemeInterestController::class, 'index'])->name('interests.index');

        // Dedicated Scheme MIS: Distribution of Wheat Seed (Cost Sharing 50:50)
        Route::middleware('role:Super Admin|Secretary|Director Agriculture|District Officer|District Deputy Director|Division Director|Field Officer|Accounts Officer|Distribution Officer')->group(function () {
            Route::get('schemes/wheat-mis', [WheatSeedMisController::class, 'index'])->name('wheat-mis.index');
            Route::get('schemes/wheat-mis/analytics', [WheatSeedMisController::class, 'analytics'])->name('wheat-mis.analytics');
            Route::get('schemes/wheat-mis/beneficiaries', [WheatSeedMisController::class, 'beneficiaries'])->name('wheat-mis.beneficiaries');
            Route::get('schemes/wheat-mis/export', [WheatSeedMisController::class, 'export'])->name('wheat-mis.export');
            Route::post('schemes/wheat-mis/rate', [WheatSeedMisController::class, 'updateRate'])->name('wheat-mis.rate');
        });

        // Stock & Distribution Management (Centers, Receipts, Transfers, Damages, Demand vs Stock)
        Route::middleware('role:Super Admin|Secretary|Director Agriculture|Director Livestock|Director Fisheries|District Officer|District Deputy Director|Division Director|Distribution Officer')->prefix('stock')->name('stock.')->group(function () {
            Route::get('/', [StockManagementController::class, 'index'])->name('index');
            Route::get('demand-vs-stock', [StockManagementController::class, 'demandVsStock'])->name('demand');
            
            // Collection Centers
            Route::get('centers', [StockManagementController::class, 'centersIndex'])->name('centers');
            Route::post('centers', [StockManagementController::class, 'centersStore'])->name('centers.store');
            Route::put('centers/{center}', [StockManagementController::class, 'centersUpdate'])->name('centers.update');
            
            // Receipts (Stock In)
            Route::get('receipts', [StockManagementController::class, 'receiptsIndex'])->name('receipts');
            Route::post('receipts', [StockManagementController::class, 'receiptsStore'])->name('receipts.store');
            
            // Transfers
            Route::get('transfers', [StockManagementController::class, 'transfersIndex'])->name('transfers');
            Route::post('transfers', [StockManagementController::class, 'transfersStore'])->name('transfers.store');
            
            // Damages / Wastages
            Route::get('damages', [StockManagementController::class, 'damagesIndex'])->name('damages');
            Route::post('damages', [StockManagementController::class, 'damagesStore'])->name('damages.store');

            // Scheme Stock Items
            Route::post('items', [StockManagementController::class, 'itemsStore'])->name('items.store');
        });

        // CM Executive Analytics Dashboard
        Route::middleware('role:Super Admin|Secretary|Director Agriculture|Director Livestock|Director Fisheries|Division Director')->group(function () {
            Route::get('cm-dashboard', [CmDashboardController::class, 'index'])->name('cm-dashboard.index');
            Route::get('cm-dashboard/analytics', [CmDashboardController::class, 'analytics'])->name('cm-dashboard.analytics');
            Route::get('cm-dashboard/export', [CmDashboardController::class, 'export'])->name('cm-dashboard.export');
        });

        // Scheme & Form Configuration (Super Admin & Directorate Heads ONLY - Secretary is executive oversight, not web modification)
        Route::middleware('role:Super Admin|Director Agriculture|Director Livestock|Director Fisheries')->group(function () {
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
        });

        // Super Admin only (System, CMS & Technical Web Application Configuration)
        Route::middleware('role:Super Admin')->group(function () {
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
            Route::get('users/{user}/assignments', [UserController::class, 'assignments'])->name('users.assignments');
            Route::post('users/{user}/assignments', [UserController::class, 'storeAssignment'])->name('users.assignments.store');
            Route::delete('users/assignments/{assignment}', [UserController::class, 'destroyAssignment'])->name('users.assignments.destroy');

            Route::get('roles-matrix', [UserController::class, 'rolesMatrix'])->name('roles.matrix');
            Route::get('audit-logs', [UserController::class, 'auditLogs'])->name('audit-logs.index');
        });
    });
});

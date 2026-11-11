<?php

use App\Http\Controllers\Admin\ApplicationDocumentController;
use App\Http\Controllers\Admin\ApplicationReviewController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\CarouselSlideController;
use App\Http\Controllers\Admin\ChallanTemplateController;
use App\Http\Controllers\Admin\CmDashboardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\DepartmentStatController;
use App\Http\Controllers\Admin\DistrictController;
use App\Http\Controllers\Admin\FarmerOrganizationController;
use App\Http\Controllers\Admin\FormBuilderController;
use App\Http\Controllers\FarmerOrganizationPublicController;
use App\Http\Controllers\Admin\GbpayController;
use App\Http\Controllers\Admin\GbpayWebhookController;
use App\Http\Controllers\Admin\SchemeController as AdminSchemeController;
use App\Http\Controllers\Admin\SchemeInterestController;
use App\Http\Controllers\Admin\SiteMediaController;
use App\Http\Controllers\Admin\SiteInfoController;
use App\Http\Controllers\Admin\SmsTemplateController;
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
Route::get('/organizations', [FarmerOrganizationPublicController::class, 'index'])->name('organizations.index');

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
        Route::get('profile/change-password', [ProfileController::class, 'showChangePassword'])->name('profile.password.show');
        Route::put('profile/change-password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
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
        Route::get('applications/{application}/edit', [ApplicationReviewController::class, 'edit'])->name('applications.edit');
        Route::put('applications/{application}', [ApplicationReviewController::class, 'update'])->name('applications.update');
        Route::post('applications/{application}/status', [ApplicationReviewController::class, 'updateStatus'])->name('applications.status');
        Route::post('applications/{application}/assign-field-officer', [ApplicationReviewController::class, 'assignFieldOfficer'])->name('applications.assign-field-officer');
        Route::post('applications/{application}/assign-survey', [ApplicationReviewController::class, 'assignSurvey'])->name('applications.assign-survey');
        Route::post('applications/surveys/{survey}/schedule', [ApplicationReviewController::class, 'scheduleSurveyVisit'])->name('applications.surveys.schedule');
        Route::post('applications/surveys/{survey}/complete', [ApplicationReviewController::class, 'completeSurveyVisit'])->name('applications.surveys.complete');
        Route::post('applications/surveys/{survey}/findings', [ApplicationReviewController::class, 'submitSurveyFindings'])->name('applications.surveys.findings');
        Route::post('applications/{application}/correction-request', [ApplicationReviewController::class, 'requestCorrection'])->name('applications.correction-request');
        Route::post('applications/{application}/verify-payment', [ApplicationReviewController::class, 'verifyPayment'])->name('applications.verify-payment');
        Route::post('applications/{application}/generate-gbpay-bill', [GbpayController::class, 'generateApplicationBill'])->middleware('role:Super Admin')->name('applications.generate-gbpay-bill');
        Route::post('applications/{application}/allocate-stock', [ApplicationReviewController::class, 'allocateStock'])->name('applications.allocate-stock');
        Route::post('applications/{application}/reactivate', [ApplicationReviewController::class, 'reactivateDormant'])->name('applications.reactivate');
        Route::post('applications/{application}/assign-dd', [ApplicationReviewController::class, 'assignDeputyDirector'])->name('applications.assign-dd');
        Route::post('applications/assignments/{assignment}/report', [ApplicationReviewController::class, 'submitTaskReport'])->name('applications.assignments.report');
        Route::get('applications/{application}/documents/{document}/download', [ApplicationDocumentController::class, 'download'])
            ->name('applications.documents.download');

        Route::get('interests', [SchemeInterestController::class, 'index'])->name('interests.index');
        Route::get('organizations', [FarmerOrganizationController::class, 'index'])->name('organizations.index');

        // Dedicated Scheme MIS: Distribution of Wheat Seed (Cost Sharing 50:50)
        Route::middleware('role_or_permission:Super Admin|Secretary|Director Agriculture|District Officer|District Deputy Director|Division Director|Field Officer|Accounts Officer|Distribution Officer|pages.wheat_mis')->group(function () {
            Route::get('schemes/wheat-mis', [WheatSeedMisController::class, 'index'])->name('wheat-mis.index');
            Route::get('schemes/wheat-mis/analytics', [WheatSeedMisController::class, 'analytics'])->name('wheat-mis.analytics');
            Route::get('schemes/wheat-mis/beneficiaries', [WheatSeedMisController::class, 'beneficiaries'])->name('wheat-mis.beneficiaries');
            Route::get('schemes/wheat-mis/export', [WheatSeedMisController::class, 'export'])->name('wheat-mis.export');
            Route::post('schemes/wheat-mis/rate', [WheatSeedMisController::class, 'updateRate'])->name('wheat-mis.rate');
        });

        // Stock & Distribution Management (Centers, Receipts, Transfers, Damages, Demand vs Stock)
        Route::middleware('role_or_permission:Super Admin|Secretary|Director Agriculture|Director Livestock|Director Fisheries|District Officer|District Deputy Director|Division Director|Distribution Officer|pages.stock')->prefix('stock')->name('stock.')->group(function () {
            Route::get('/', [StockManagementController::class, 'index'])->name('index');
            Route::get('demand-vs-stock', [StockManagementController::class, 'demandVsStock'])->name('demand');
            
            // Collection Centers
            Route::get('centers', [StockManagementController::class, 'centersIndex'])->name('centers');
            Route::post('centers', [StockManagementController::class, 'centersStore'])->name('centers.store');
            Route::put('centers/{center}', [StockManagementController::class, 'centersUpdate'])->name('centers.update');
            
            // Receipts (Stock In)
            Route::get('receipts', [StockManagementController::class, 'receiptsIndex'])->name('receipts');
            Route::post('receipts', [StockManagementController::class, 'receiptsStore'])->name('receipts.store');
            Route::put('receipts/{receipt}', [StockManagementController::class, 'receiptsUpdate'])->name('receipts.update');
            
            // Transfers
            Route::get('transfers', [StockManagementController::class, 'transfersIndex'])->name('transfers');
            Route::post('transfers', [StockManagementController::class, 'transfersStore'])->name('transfers.store');
            
            // Damages / Wastages
            Route::get('damages', [StockManagementController::class, 'damagesIndex'])->name('damages');
            Route::post('damages', [StockManagementController::class, 'damagesStore'])->name('damages.store');

            // Scheme Stock Items
            Route::post('items', [StockManagementController::class, 'itemsStore'])->name('items.store');

            // Disbursements (Stock Out to Eligible Beneficiaries)
            Route::get('disbursements', [StockManagementController::class, 'disbursementsIndex'])->name('disbursements');
            Route::post('disbursements', [StockManagementController::class, 'disbursementsStore'])->name('disbursements.store');
            Route::put('disbursements/{disbursement}', [StockManagementController::class, 'disbursementsUpdate'])->name('disbursements.update');
            Route::get('disbursements/{disbursement}/voucher', [StockManagementController::class, 'disbursementVoucher'])->name('disbursements.voucher');
        });

        // CM Executive Analytics Dashboard
        Route::middleware('role_or_permission:Super Admin|Secretary|Director Agriculture|Director Livestock|Director Fisheries|Division Director|pages.cm_dashboard')->group(function () {
            Route::get('cm-dashboard', [CmDashboardController::class, 'index'])->name('cm-dashboard.index');
            Route::get('cm-dashboard/analytics', [CmDashboardController::class, 'analytics'])->name('cm-dashboard.analytics');
            Route::get('cm-dashboard/export', [CmDashboardController::class, 'export'])->name('cm-dashboard.export');
        });

        // Scheme & Form Configuration (Super Admin & Directorate Heads ONLY - Secretary is executive oversight, not web modification)
        Route::middleware('role_or_permission:Super Admin|Director Agriculture|Director Livestock|Director Fisheries|pages.schemes|pages.stats|pages.form_builder|pages.challan_templates')->group(function () {
            Route::resource('schemes', AdminSchemeController::class);
            Route::resource('challan-templates', ChallanTemplateController::class)->names('challan-templates');
            Route::get('challan-templates/{challan_template}/preview', [ChallanTemplateController::class, 'preview'])->name('challan-templates.preview');

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

        // System, CMS & Technical Web Application Configuration (Super Admin or Delegated Page Permissions)
        Route::middleware('role_or_permission:Super Admin|pages.departments|pages.districts|pages.organizations|pages.cms|pages.users|pages.roles|pages.audit_logs')->group(function () {
            // SMS Manual Application Dispatch & Gateway Testing (Admin roles only)
            Route::middleware('role:Super Admin')->group(function () {
                Route::post('applications/{application}/send-sms', [SmsTemplateController::class, 'sendForApplication'])->name('applications.send-sms');
                Route::post('sms-templates/gateway', [SmsTemplateController::class, 'updateGateway'])->name('sms-templates.gateway.update');
                Route::post('sms-templates/check-balance', [SmsTemplateController::class, 'checkBalance'])->name('sms-templates.check-balance');
                Route::post('sms-templates/send-test', [SmsTemplateController::class, 'sendTest'])->name('sms-templates.send-test');
                Route::post('sms-templates/{sms_template}/toggle', [SmsTemplateController::class, 'toggle'])->name('sms-templates.toggle');
                Route::resource('sms-templates', SmsTemplateController::class)->except(['show'])->names('sms-templates');
            });

            Route::middleware('role_or_permission:Super Admin|pages.departments')->group(function () {
                Route::resource('departments', AdminDepartmentController::class);
            });

            Route::middleware('role_or_permission:Super Admin|pages.districts')->group(function () {
                Route::get('districts', [DistrictController::class, 'index'])->name('districts.index');
                Route::post('districts', [DistrictController::class, 'store'])->name('districts.store');
                Route::put('districts/{district}', [DistrictController::class, 'update'])->name('districts.update');

                // Tehsils CRUD against District
                Route::post('districts/{district}/tehsils', [DistrictController::class, 'storeTehsil'])->name('districts.tehsils.store');
                Route::post('tehsils', [DistrictController::class, 'storeTehsilGeneral'])->name('tehsils.store');
                Route::put('tehsils/{tehsil}', [DistrictController::class, 'updateTehsil'])->name('tehsils.update');
                Route::delete('tehsils/{tehsil}', [DistrictController::class, 'destroyTehsil'])->name('tehsils.destroy');

                // Union Councils CRUD against Tehsil / District
                Route::post('tehsils/{tehsil}/union-councils', [DistrictController::class, 'storeUnionCouncil'])->name('tehsils.union-councils.store');
                Route::post('union-councils', [DistrictController::class, 'storeUnionCouncilGeneral'])->name('union-councils.store');
                Route::put('union-councils/{unionCouncil}', [DistrictController::class, 'updateUnionCouncil'])->name('union-councils.update');
                Route::delete('union-councils/{unionCouncil}', [DistrictController::class, 'destroyUnionCouncil'])->name('union-councils.destroy');
            });

            // Farmer / Village Organizations CRUD
            Route::middleware('role_or_permission:Super Admin|pages.organizations')->group(function () {
                Route::post('organizations', [FarmerOrganizationController::class, 'store'])->name('organizations.store');
                Route::put('organizations/{organization}', [FarmerOrganizationController::class, 'update'])->name('organizations.update');
                Route::delete('organizations/{organization}', [FarmerOrganizationController::class, 'destroy'])->name('organizations.destroy');
            });

            Route::middleware('role_or_permission:Super Admin|pages.cms')->group(function () {
                Route::resource('carousel', CarouselSlideController::class)->except(['show']);
                Route::get('media', [SiteMediaController::class, 'edit'])->name('media.edit');
                Route::put('media', [SiteMediaController::class, 'update'])->name('media.update');
                Route::get('site-info', [SiteInfoController::class, 'edit'])->name('site-info.edit');
                Route::put('site-info', [SiteInfoController::class, 'update'])->name('site-info.update');
            });

            Route::middleware('role_or_permission:Super Admin|pages.users')->group(function () {
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::get('users/create', [UserController::class, 'create'])->name('users.create');
                Route::post('users', [UserController::class, 'store'])->name('users.store');
                Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
                Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::get('users/{user}/assignments', [UserController::class, 'assignments'])->name('users.assignments');
                Route::post('users/{user}/assignments', [UserController::class, 'storeAssignment'])->name('users.assignments.store');
                Route::delete('users/assignments/{assignment}', [UserController::class, 'destroyAssignment'])->name('users.assignments.destroy');
            });

            Route::middleware('role_or_permission:Super Admin|pages.roles')->group(function () {
                Route::get('roles-matrix', [UserController::class, 'rolesMatrix'])->name('roles.matrix');
            });

            Route::middleware('role_or_permission:Super Admin|pages.audit_logs')->group(function () {
                Route::get('audit-logs', [UserController::class, 'auditLogs'])->name('audit-logs.index');
            });
        });

        // GBPay Payment Gateway Configuration, Credentials & PSID Ledger (Admin roles only)
        Route::middleware('role:Super Admin')->prefix('gbpay')->name('gbpay.')->group(function () {
            Route::get('/', [GbpayController::class, 'index'])->name('index');
            Route::match(['post', 'put'], 'psid-config', [GbpayController::class, 'updatePsidConfig'])->name('psid-config.update');
            Route::delete('psid-config/{id}', [GbpayController::class, 'destroySchemePsidConfig'])->name('psid-config.destroy');
            Route::post('credentials', [GbpayController::class, 'storeCredential'])->name('credentials.store');
            Route::put('credentials/{id}', [GbpayController::class, 'updateCredential'])->name('credentials.update');
            Route::delete('credentials/{id}', [GbpayController::class, 'destroyCredential'])->name('credentials.destroy');

            // Interactive GBPay API Testing & Sandbox Console
            Route::get('test', [GbpayController::class, 'testConsole'])->name('test');
            Route::post('test/execute', [GbpayController::class, 'executeTest'])->name('test.execute');
            Route::post('test/sample-bill', [GbpayController::class, 'createSampleBill'])->name('test.sample-bill');
            Route::post('test/reset-bill/{bill}', [GbpayController::class, 'resetBillToPending'])->name('test.reset-bill');
        });
    });
});

// Public GBPay Webhook Route (No Auth Required)
Route::post('/gbpay/webhook', [GbpayWebhookController::class, 'handle'])->name('gbpay.webhook');

<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Geometry\BarangayRegistryController;
use App\Http\Controllers\Admin\Geometry\HouseholdController;
use App\Http\Controllers\Admin\Geometry\PurokGridController;
use App\Http\Controllers\Admin\Geometry\ResidentController;
use App\Http\Controllers\Admin\IAM\UserApprovalController;
use App\Http\Controllers\Admin\IAM\UserController;
use App\Http\Controllers\Admin\IAM\UserPasswordController;
use App\Http\Controllers\Admin\IAM\UserStatusController;
use App\Http\Controllers\Admin\Maintenance\BackupController;
use App\Http\Controllers\Admin\Maintenance\DataArchiveController;
use App\Http\Controllers\Admin\Maintenance\SystemMetricsController;
use App\Http\Controllers\Admin\Oversight\ClinicalOversightController;
use App\Http\Controllers\Admin\Oversight\FieldOperationsMonitorController;
use App\Http\Controllers\Admin\Oversight\NutritionOversightController;
use App\Http\Controllers\Admin\Reports\MunicipalReportController;
use App\Http\Controllers\Admin\RateLimitController;
use App\Http\Controllers\Admin\Security\AuditTrailController;
use App\Http\Controllers\Admin\Security\MobileDeviceController;
use App\Http\Controllers\Admin\Security\SyncLogController;
use App\Http\Controllers\Bns\CampaignPeriodController as BnsCampaignPeriodController;
use App\Http\Controllers\Bns\DashboardController as BnsDashboardController;
use App\Http\Controllers\Bns\FeedingProgramController as BnsFeedingProgramController;
use App\Http\Controllers\Bns\MaternalTrackingController as BnsMaternalTrackingController;
use App\Http\Controllers\Bns\MicronutrientLogController as BnsMicronutrientLogController;
use App\Http\Controllers\Bns\OptMeasurementController as BnsOptMeasurementController;
use App\Http\Controllers\Bns\TargetClientListController as BnsTargetClientListController;
use App\Http\Controllers\Bhw\CampaignTaskController as BhwCampaignTaskController;
use App\Http\Controllers\Bhw\DashboardController as BhwDashboardController;
use App\Http\Controllers\Bhw\HouseholdController as BhwHouseholdController;
use App\Http\Controllers\Bhw\HouseholdDraftController as BhwHouseholdDraftController;
use App\Http\Controllers\Bhw\NutritionFlagController as BhwNutritionFlagController;
use App\Http\Controllers\Bhw\ResidentController as BhwResidentController;
use App\Http\Controllers\Bhw\TriageController as BhwTriageController;
use App\Http\Controllers\Bhw\UpdateRequestController as BhwUpdateRequestController;
use App\Http\Controllers\Mho\ClinicalReviewController as MhoClinicalReviewController;
use App\Http\Controllers\Mho\DashboardController as MhoDashboardController;
use App\Http\Controllers\Mho\EscalationController as MhoEscalationController;
use App\Http\Controllers\Mho\ResidentController as MhoResidentController;
use App\Http\Controllers\Phn\ClinicalEncounterController as PhnClinicalEncounterController;
use App\Http\Controllers\Phn\DashboardController as PhnDashboardController;
use App\Http\Controllers\Phn\FollowUpController as PhnFollowUpController;
use App\Http\Controllers\Phn\ResidentController as PhnResidentController;
use App\Http\Controllers\Phn\TriageQueueController as PhnTriageQueueController;
use App\Http\Controllers\Phn\UpdateRequestController as PhnUpdateRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Secretary\ActivityFeedController as SecretaryActivityFeedController;
use App\Http\Controllers\Secretary\CertificateController as SecretaryCertificateController;
use App\Http\Controllers\Secretary\DashboardController as SecretaryDashboardController;
use App\Http\Controllers\Secretary\DemographicReportController as SecretaryDemographicReportController;
use App\Http\Controllers\Secretary\FieldDraftController as SecretaryFieldDraftController;
use App\Http\Controllers\Secretary\FrontlineUserController as SecretaryFrontlineUserController;
use App\Http\Controllers\Secretary\HouseholdController as SecretaryHouseholdController;
use App\Http\Controllers\Secretary\PurokController as SecretaryPurokController;
use App\Http\Controllers\Secretary\ResidentController as SecretaryResidentController;
use App\Http\Controllers\Secretary\TriageQueueController as SecretaryTriageQueueController;
use App\Http\Controllers\Secretary\UpdateRequestController as SecretaryUpdateRequestController;
use App\Http\Controllers\Secretary\UserPasswordController as SecretaryUserPasswordController;
use Illuminate\Support\Facades\Route;

// =============================================
// PUBLIC ROUTES
// =============================================

Route::get('/', function () {
    return request()->user()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->middleware('no-cache');

Route::get('/dashboard', function () {
    $user = request()->user();

    if ($user) {
        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'bns' => redirect()->route('bns.dashboard'),
            'secretary' => redirect()->route('secretary.dashboard'),
            'bhw' => redirect()->route('bhw.dashboard'),
            'phn' => redirect()->route('phn.dashboard'),
            'mho' => redirect()->route('mho.dashboard'),
            default => view('dashboard'),
        };
    }

    return view('dashboard');
})->middleware(['auth', 'active', 'no-cache'])->name('dashboard');

// =============================================
// AUTHENTICATION ROUTES (Breeze handles these)
// =============================================
require __DIR__.'/auth.php';

// =============================================
// AUTHENTICATED ROUTES
// =============================================

Route::middleware(['auth', 'no-cache'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =============================================
// BNS ROUTES (Protected by 'auth', 'active', 'role:bns')
// =============================================

Route::middleware(['auth', 'active', 'role:bns', 'no-cache'])
    ->prefix('bns')
    ->name('bns.')
    ->group(function () {
        Route::get('/dashboard', BnsDashboardController::class)->name('dashboard');

        Route::prefix('campaign-periods')
            ->name('campaign-periods.')
            ->controller(BnsCampaignPeriodController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{campaignPeriod}/edit', 'edit')->name('edit');
                Route::put('/{campaignPeriod}', 'update')->name('update');
            });

        Route::prefix('opt-measurements')
            ->name('opt-measurements.')
            ->controller(BnsOptMeasurementController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{optMeasurement}', 'show')->name('show');
            });

        Route::prefix('watchlist')
            ->name('watchlist.')
            ->controller(BnsTargetClientListController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
            });

        Route::prefix('feeding-programs')
            ->name('feeding-programs.')
            ->controller(BnsFeedingProgramController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{feedingProgram}', 'show')->name('show');
                Route::get('/{feedingProgram}/edit', 'edit')->name('edit');
                Route::put('/{feedingProgram}', 'update')->name('update');
                Route::post('/{feedingProgram}/enrollments', 'storeEnrollment')->name('enrollments.store');
                Route::patch('/{feedingProgram}/enrollments/{enrollment}', 'updateEnrollment')->name('enrollments.update');
                Route::post('/{feedingProgram}/enrollments/{enrollment}/attendances', 'storeAttendance')->name('attendances.store');
                Route::post('/{feedingProgram}/enrollments/{enrollment}/progress', 'storeProgress')->name('progress.store');
            });

        Route::prefix('maternal-tracking')
            ->name('maternal.')
            ->controller(BnsMaternalTrackingController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/profile', 'upsertProfile')->name('profile.store');
                Route::get('/{resident}', 'show')->name('show');
                Route::put('/{resident}/profile', 'upsertProfile')->name('profile.update');
                Route::post('/{resident}/histories', 'storeHistory')->name('histories.store');
                Route::post('/{resident}/infant-feeding-logs', 'storeInfantFeedingLog')->name('infant-feeding.store');
            });

        Route::prefix('micronutrients')
            ->name('micronutrients.')
            ->controller(BnsMicronutrientLogController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });
    });

// =============================================
// BHW ROUTES (Protected by 'auth', 'active', 'role:bhw')
// =============================================

Route::middleware(['auth', 'active', 'role:bhw', 'no-cache'])
    ->prefix('bhw')
    ->name('bhw.')
    ->group(function () {
        Route::get('/dashboard', BhwDashboardController::class)->name('dashboard');

        Route::prefix('residents')
            ->name('residents.')
            ->controller(BhwResidentController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{resident}', 'show')->name('show');
            });

        Route::prefix('households')
            ->name('households.')
            ->controller(BhwHouseholdController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{household}', 'show')->name('show');
            });

        Route::prefix('drafts')
            ->name('drafts.')
            ->controller(BhwHouseholdDraftController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{householdDraft}', 'show')->name('show');
                Route::get('/{householdDraft}/edit', 'edit')->name('edit');
                Route::put('/{householdDraft}', 'update')->name('update');
            });

        Route::prefix('update-requests')
            ->name('update-requests.')
            ->controller(BhwUpdateRequestController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/residents/create', 'createResident')->name('create-resident');
                Route::post('/residents', 'storeResident')->name('store-resident');
                Route::get('/households/create', 'createHousehold')->name('create-household');
                Route::post('/households', 'storeHousehold')->name('store-household');
                Route::get('/{profileUpdateRequest}', 'show')->name('show');
            });

        Route::prefix('triage')
            ->name('triage.')
            ->controller(BhwTriageController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{triageRecord}', 'show')->name('show');
                Route::get('/{triageRecord}/edit', 'edit')->name('edit');
                Route::put('/{triageRecord}', 'update')->name('update');
            });

        Route::prefix('nutrition-flags')
            ->name('nutrition-flags.')
            ->controller(BhwNutritionFlagController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
            });

        Route::prefix('campaigns')
            ->name('campaigns.')
            ->controller(BhwCampaignTaskController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{assignment}', 'show')->name('show');
                Route::patch('/{assignment}', 'update')->name('update');
            });
    });

// =============================================
// SECRETARY ROUTES (Protected by 'auth', 'active', 'role:secretary')
// =============================================

Route::middleware(['auth', 'active', 'role:secretary', 'no-cache'])
    ->prefix('secretary')
    ->name('secretary.')
    ->group(function () {
        Route::get('/dashboard', SecretaryDashboardController::class)->name('dashboard');

        Route::prefix('puroks')
            ->name('puroks.')
            ->controller(SecretaryPurokController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{purok}', 'show')->name('show');
                Route::get('/{purok}/edit', 'edit')->name('edit');
                Route::put('/{purok}', 'update')->name('update');
                Route::patch('/{purok}/toggle-status', 'toggleStatus')->name('toggle-status');
            });

        Route::prefix('households')
            ->name('households.')
            ->controller(SecretaryHouseholdController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{household}/pdf', 'pdf')->name('pdf');
                Route::get('/{household}', 'show')->name('show');
                Route::get('/{household}/edit', 'edit')->name('edit');
                Route::put('/{household}', 'update')->name('update');
                Route::patch('/{household}/toggle-status', 'toggleStatus')->name('toggle-status');
            });

        Route::prefix('residents')
            ->name('residents.')
            ->controller(SecretaryResidentController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{resident}/pdf', 'pdf')->name('pdf');
                Route::get('/{resident}/print', 'printView')->name('print');
                Route::get('/households-by-purok', 'householdsByPurok')->name('households-by-purok');
                Route::get('/{resident}/relocate', 'editRelocation')->name('relocate.edit');
                Route::patch('/{resident}/relocate', 'relocate')->name('relocate.update');
                Route::get('/{resident}', 'show')->name('show');
                Route::get('/{resident}/edit', 'edit')->name('edit');
                Route::put('/{resident}', 'update')->name('update');
                Route::patch('/{resident}/toggle-status', 'toggleStatus')->name('toggle-status');
            });

        Route::prefix('certificates')
            ->name('certificates.')
            ->controller(SecretaryCertificateController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{certificate}/pdf', 'pdf')->name('pdf');
                Route::get('/{certificate}', 'show')->name('show');
            });

        Route::prefix('team')
            ->name('team.')
            ->controller(SecretaryFrontlineUserController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::patch('/{user}/approve', 'approve')->name('approve');
                Route::patch('/{user}/reject', 'reject')->name('reject');
                Route::get('/{user}', 'show')->name('show');
                Route::get('/{user}/edit', 'edit')->name('edit');
                Route::put('/{user}', 'update')->name('update');
            });

        Route::prefix('team')
            ->name('team.')
            ->controller(SecretaryUserPasswordController::class)
            ->group(function () {
                Route::get('/{user}/password', 'edit')->name('password.edit');
                Route::put('/{user}/password', 'reset')->name('password.reset');
                Route::post('/{user}/password/generate', 'generateTemporary')->name('password.generate');
            });

        Route::prefix('draft-packages')
            ->name('drafts.')
            ->controller(SecretaryFieldDraftController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{householdDraft}', 'show')->name('show');
                Route::get('/{householdDraft}/edit', 'edit')->name('edit');
                Route::patch('/{householdDraft}/approve', 'approve')->name('approve');
                Route::patch('/{householdDraft}/reject', 'reject')->name('reject');
            });

        Route::prefix('update-requests')
            ->name('update-requests.')
            ->controller(SecretaryUpdateRequestController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{profileUpdateRequest}', 'show')->name('show');
                Route::get('/{profileUpdateRequest}/edit', 'edit')->name('edit');
                Route::patch('/{profileUpdateRequest}/approve', 'approve')->name('approve');
                Route::patch('/{profileUpdateRequest}/reject', 'reject')->name('reject');
            });

        Route::prefix('triage-queue')
            ->name('triage.')
            ->controller(SecretaryTriageQueueController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{triageRecord}', 'show')->name('show');
            });

        Route::prefix('activity')
            ->name('activity.')
            ->controller(SecretaryActivityFeedController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export', 'export')->name('export');
                Route::get('/{auditLog}', 'show')->name('show');
            });

        Route::prefix('reports')
            ->name('reports.')
            ->controller(SecretaryDemographicReportController::class)
            ->group(function () {
                Route::get('/demographics', 'index')->name('demographics');
                Route::get('/demographics/export/{format}', 'export')->name('demographics.export');
            });

        Route::get('/puroks/get-by-barangay', [SecretaryPurokController::class, 'getByBarangay'])->name('puroks.get-by-barangay');
    });

// =============================================
// PHN ROUTES (Protected by 'auth', 'active', 'role:phn')
// =============================================

Route::middleware(['auth', 'active', 'role:phn', 'no-cache'])
    ->prefix('phn')
    ->name('phn.')
    ->group(function () {
        Route::get('/dashboard', PhnDashboardController::class)->name('dashboard');

        Route::prefix('triage-queue')
            ->name('triage.')
            ->controller(PhnTriageQueueController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{triageRecord}', 'show')->name('show');
            });

        Route::prefix('encounters')
            ->name('encounters.')
            ->controller(PhnClinicalEncounterController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{clinicalEncounter}/pdf', 'pdf')->name('pdf');
                Route::get('/{clinicalEncounter}', 'show')->name('show');
                Route::get('/{clinicalEncounter}/edit', 'edit')->name('edit');
                Route::put('/{clinicalEncounter}', 'update')->name('update');
            });

        Route::prefix('follow-ups')
            ->name('follow-ups.')
            ->controller(PhnFollowUpController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::patch('/{clinicalEncounter}', 'update')->name('update');
            });

        Route::prefix('residents')
            ->name('residents.')
            ->controller(PhnResidentController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{resident}', 'show')->name('show');
            });

        Route::prefix('update-requests')
            ->name('update-requests.')
            ->controller(PhnUpdateRequestController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/residents/create', 'createResident')->name('create-resident');
                Route::post('/residents', 'storeResident')->name('store-resident');
                Route::get('/households/create', 'createHousehold')->name('create-household');
                Route::post('/households', 'storeHousehold')->name('store-household');
                Route::get('/{profileUpdateRequest}', 'show')->name('show');
            });
    });

// =============================================
// MHO ROUTES (Protected by 'auth', 'active', 'role:mho')
// =============================================

Route::middleware(['auth', 'active', 'role:mho', 'no-cache'])
    ->prefix('mho')
    ->name('mho.')
    ->group(function () {
        Route::get('/dashboard', MhoDashboardController::class)->name('dashboard');

        Route::prefix('escalations')
            ->name('escalations.')
            ->controller(MhoEscalationController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/export/{format}', 'export')->name('export');
                Route::get('/{clinicalEncounter}/pdf', 'pdf')->name('pdf');
                Route::get('/{clinicalEncounter}', 'show')->name('show');
            });

        Route::prefix('reviews')
            ->name('reviews.')
            ->controller(MhoClinicalReviewController::class)
            ->group(function () {
                Route::get('/{clinicalEncounter}/create', 'create')->name('create');
                Route::post('/{clinicalEncounter}', 'store')->name('store');
                Route::get('/{clinicalEncounter}/edit', 'edit')->name('edit');
                Route::put('/{clinicalEncounter}', 'update')->name('update');
            });

        Route::prefix('residents')
            ->name('residents.')
            ->controller(MhoResidentController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/{resident}', 'show')->name('show');
            });
    });

// =============================================
// ADMIN ROUTES (Protected by 'auth', 'active', 'role:admin')
// =============================================

Route::middleware(['auth', 'active', 'role:admin', 'no-cache'])
     ->prefix('admin')
     ->name('admin.')
     ->group(function () {

    // =============================================
    // ADMIN DASHBOARD
    // =============================================
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // =============================================
    // 1. IAM (Identity & Access Management)
    // =============================================
    Route::prefix('users')
         ->name('users.')
         ->controller(UserController::class)
         ->group(function () {
            // Main CRUD
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/export/{format}', 'export')->name('export');

            // AJAX endpoint for loading puroks
            Route::get('/get-puroks', 'getPuroks')->name('get-puroks');
            Route::get('/{user}', 'show')->name('show');
            Route::get('/{user}/edit', 'edit')->name('edit');
            Route::put('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('destroy');
            Route::patch('/{user}/restore', 'restore')->name('restore');
         });

    // User Status Management
    Route::prefix('users')
         ->name('users.')
         ->controller(UserApprovalController::class)
         ->group(function () {
            Route::patch('/{user}/approve', 'approve')->name('approve');
            Route::patch('/{user}/reject', 'reject')->name('reject');
         });

    Route::prefix('users')
         ->name('users.')
         ->controller(UserStatusController::class)
         ->group(function () {
            Route::patch('/{user}/toggle-status', 'toggle')->name('toggle-status');
            Route::post('/bulk-toggle-status', 'bulkToggle')->name('bulk-toggle-status');
         });

    // User Password Management
    Route::prefix('users')
         ->name('users.')
         ->controller(UserPasswordController::class)
         ->group(function () {
            Route::get('/{user}/password', 'edit')->name('password.edit');
            Route::put('/{user}/password', 'reset')->name('password.reset');
            Route::post('/{user}/password/generate', 'generateTemporary')->name('password.generate');
         });

    // =============================================
    // 2. GEOMETRY (Barangays & Puroks)
    // =============================================
    Route::prefix('barangays')
         ->name('barangays.')
         ->controller(BarangayRegistryController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/export/{format}', 'export')->name('export');
            Route::get('/{barangay}', 'show')->name('show');
            Route::get('/{barangay}/edit', 'edit')->name('edit');
            Route::put('/{barangay}', 'update')->name('update');
            Route::delete('/{barangay}', 'destroy')->name('destroy');
            Route::patch('/{barangay}/restore', 'restore')->name('restore');
            Route::patch('/{barangay}/toggle-status', 'toggleStatus')->name('toggle-status');
         });

    Route::prefix('puroks')
         ->name('puroks.')
         ->controller(PurokGridController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');

            // AJAX endpoint for loading puroks by barangay
            Route::get('/get-by-barangay', 'getByBarangay')->name('get-by-barangay');
            Route::get('/export/{format}', 'export')->name('export');
            Route::get('/{purok}', 'show')->name('show');
            Route::get('/{purok}/edit', 'edit')->name('edit');
            Route::put('/{purok}', 'update')->name('update');
            Route::delete('/{purok}', 'destroy')->name('destroy');
            Route::patch('/{purok}/restore', 'restore')->name('restore');
            Route::patch('/{purok}/toggle-status', 'toggleStatus')->name('toggle-status');
         });

    Route::prefix('households')
         ->name('households.')
         ->controller(HouseholdController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/export/{format}', 'export')->name('export');
            Route::get('/{household}/pdf', 'pdf')->name('pdf');
            Route::get('/{household}', 'show')->name('show');
            Route::get('/{household}/edit', 'edit')->name('edit');
            Route::put('/{household}', 'update')->name('update');
            Route::delete('/{household}', 'destroy')->name('destroy');
            Route::patch('/{household}/toggle-status', 'toggleStatus')->name('toggle-status');
            Route::patch('/{id}/restore', 'restore')->name('restore');
         });

    Route::prefix('residents')
         ->name('residents.')
         ->controller(ResidentController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/export/{format}', 'export')->name('export');
            Route::get('/{resident}/pdf', 'pdf')->name('pdf');
            Route::get('/{resident}/print', 'printView')->name('print');
            Route::get('/households-by-purok', 'householdsByPurok')->name('households-by-purok');
            Route::get('/{resident}', 'show')->name('show');
            Route::get('/{resident}/edit', 'edit')->name('edit');
            Route::put('/{resident}', 'update')->name('update');
            Route::delete('/{resident}', 'destroy')->name('destroy');
            Route::patch('/{resident}/toggle-status', 'toggleStatus')->name('toggle-status');
            Route::patch('/{id}/restore', 'restore')->name('restore');
         });

    // =============================================
    // 3. OVERSIGHT (Read-Only Municipal Monitors)
    // =============================================
    Route::prefix('oversight')
         ->name('oversight.')
         ->group(function () {
            Route::get('/field-operations', FieldOperationsMonitorController::class)->name('field');
            Route::get('/nutrition', NutritionOversightController::class)->name('nutrition');
            Route::get('/clinical', ClinicalOversightController::class)->name('clinical');
         });

    Route::prefix('reports')
         ->name('reports.')
         ->controller(MunicipalReportController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{report}/{format}', 'export')->name('export');
         });

    // =============================================
    // 4. SECURITY (Audit Trail)
    // =============================================
    Route::prefix('audit')
         ->name('audit.')
         ->controller(AuditTrailController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::get('/{auditLog}', 'show')->name('show');
            Route::delete('/clear-old', 'clearOld')->name('clear-old');
         });

    // =============================================
    // 5. DEVICES (Mobile & Sync)
    // =============================================
    Route::prefix('devices')
         ->name('devices.')
         ->controller(MobileDeviceController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/export', 'export')->name('export');
            Route::post('/issue', 'issue')->name('issue');
            Route::delete('/{tokenId}/revoke', 'revoke')->name('revoke');
            Route::delete('/user/{user}/revoke-all', 'revokeAll')->name('revoke-all');
         });

    Route::prefix('sync-logs')
         ->name('sync-logs.')
         ->controller(SyncLogController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/stats', 'stats')->name('stats');
            Route::get('/export', 'export')->name('export');
            Route::get('/{syncLog}', 'show')->name('show');
            Route::delete('/clear-old', 'clearOld')->name('clear-old');
         });

    // =============================================
    // 6. MAINTENANCE (Backups, Archive, Metrics)
    // =============================================
    Route::prefix('backups')
         ->name('backups.')
         ->controller(BackupController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/generate', 'generate')->name('generate');
            Route::get('/{backup}', 'show')->name('show');
            Route::post('/{backup}/verify', 'verify')->name('verify');
            Route::post('/{backup}/restore', 'restore')->name('restore');
            Route::get('/{backup}/download', 'download')->name('download');
            Route::delete('/delete-expired', 'deleteExpired')->name('delete-expired');
            Route::delete('/{backup}', 'destroy')->name('destroy');
         });

    Route::prefix('archive')
         ->name('archive.')
         ->controller(DataArchiveController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::get('/search', 'search')->name('search');
            Route::post('/', 'store')->name('store');
            Route::get('/{archivedRecord}', 'show')->name('show');
            Route::patch('/{archivedRecord}/restore', 'restore')->name('restore');
            Route::delete('/{archivedRecord}/purge', 'purge')->name('purge');
         });

    Route::prefix('metrics')
         ->name('metrics.')
         ->controller(SystemMetricsController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/query-performance', 'queryPerformance')->name('query-performance');
            Route::get('/health', 'health')->name('health');
         });

    // =============================================
    // 7. RATE LIMITS
    // =============================================
    Route::prefix('rate-limits')
         ->name('rate-limits.')
         ->controller(RateLimitController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
            Route::post('/reset', 'reset')->name('reset');
            Route::get('/status', 'status')->name('status');
            Route::get('/blocked', 'blocked')->name('blocked');
            Route::delete('/unblock', 'unblock')->name('unblock');
         });
});

// =============================================
// API ROUTES (Protected by 'auth', 'active')
// =============================================
Route::middleware(['auth', 'active', 'role:admin', 'dynamic-throttle:admin-helper'])
     ->prefix('api/admin')
     ->name('api.admin.')
     ->group(function () {
        // Sync Logs Stats API (used by dashboard)
        Route::get('/sync-stats', [SyncLogController::class, 'stats'])->name('sync-stats');

        // System Health API
        Route::get('/health', [SystemMetricsController::class, 'health'])->name('health');

        // Rate Limit Status API
        Route::get('/rate-limit-status', [RateLimitController::class, 'status'])->name('rate-limit-status');

        // Get Puroks by Barangay API
        Route::get('/puroks-by-barangay', [PurokGridController::class, 'getByBarangay'])->name('puroks-by-barangay');

        // Get Puroks for User Form API
        Route::get('/user-puroks', [UserController::class, 'getPuroks'])->name('user-puroks');

        // Search Archive API
        Route::get('/archive-search', [DataArchiveController::class, 'search'])->name('archive-search');
    });

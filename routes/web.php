<?php

use App\Http\Controllers\Admin\Devices\MobileDeviceController;
use App\Http\Controllers\Admin\Devices\SyncLogController;
use App\Http\Controllers\Admin\Geometry\BarangayRegistryController;
use App\Http\Controllers\Admin\Geometry\PurokGridController;
use App\Http\Controllers\Admin\IAM\UserController;
use App\Http\Controllers\Admin\IAM\UserPasswordController;
use App\Http\Controllers\Admin\IAM\UserStatusController;
use App\Http\Controllers\Admin\Maintenance\BackupController;
use App\Http\Controllers\Admin\Maintenance\DataArchiveController;
use App\Http\Controllers\Admin\Maintenance\SystemMetricsController;
use App\Http\Controllers\Admin\RateLimitController;
use App\Http\Controllers\Admin\Security\AuditTrailController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// =============================================
// PUBLIC ROUTES
// =============================================

// Redirect root to login
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'active'])->name('dashboard');

// =============================================
// AUTHENTICATION ROUTES (Breeze handles these)
// =============================================
require __DIR__.'/auth.php';

// =============================================
// AUTHENTICATED ROUTES
// =============================================

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// =============================================
// ADMIN ROUTES (Protected by 'auth', 'active', 'role:admin')
// =============================================

Route::middleware(['auth', 'active', 'role:admin'])
     ->prefix('admin')
     ->name('admin.')
     ->group(function () {

    // =============================================
    // ADMIN DASHBOARD
    // =============================================
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

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
            Route::get('/{user}', 'show')->name('show');
            Route::get('/{user}/edit', 'edit')->name('edit');
            Route::put('/{user}', 'update')->name('update');
            Route::delete('/{user}', 'destroy')->name('destroy');
            Route::patch('/{user}/restore', 'restore')->name('restore');

            // AJAX endpoint for loading puroks
            Route::get('/get-puroks', 'getPuroks')->name('get-puroks');
         });

    // User Status Management
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
            Route::get('/{purok}', 'show')->name('show');
            Route::get('/{purok}/edit', 'edit')->name('edit');
            Route::put('/{purok}', 'update')->name('update');
            Route::delete('/{purok}', 'destroy')->name('destroy');
            Route::patch('/{purok}/restore', 'restore')->name('restore');
            Route::patch('/{purok}/toggle-status', 'toggleStatus')->name('toggle-status');

            // AJAX endpoint for loading puroks by barangay
            Route::get('/get-by-barangay', 'getByBarangay')->name('get-by-barangay');
         });

    // =============================================
    // 3. SECURITY (Audit Trail)
    // =============================================
    Route::prefix('audit')
         ->name('audit.')
         ->controller(AuditTrailController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{auditLog}', 'show')->name('show');
            Route::get('/export', 'export')->name('export');
            Route::delete('/clear-old', 'clearOld')->name('clear-old');
         });

    // =============================================
    // 4. DEVICES (Mobile & Sync)
    // =============================================
    Route::prefix('devices')
         ->name('devices.')
         ->controller(MobileDeviceController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::delete('/{tokenId}/revoke', 'revoke')->name('revoke');
            Route::delete('/user/{user}/revoke-all', 'revokeAll')->name('revoke-all');
         });

    Route::prefix('sync-logs')
         ->name('sync-logs.')
         ->controller(SyncLogController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{syncLog}', 'show')->name('show');
            Route::get('/stats', 'stats')->name('stats');
            Route::get('/export', 'export')->name('export');
            Route::delete('/clear-old', 'clearOld')->name('clear-old');
         });

    // =============================================
    // 5. MAINTENANCE (Backups, Archive, Metrics)
    // =============================================
    Route::prefix('backups')
         ->name('backups.')
         ->controller(BackupController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/generate', 'generate')->name('generate');
            Route::get('/{backup}/download', 'download')->name('download');
            Route::delete('/{backup}', 'destroy')->name('destroy');
            Route::delete('/delete-expired', 'deleteExpired')->name('delete-expired');
         });

    Route::prefix('archive')
         ->name('archive.')
         ->controller(DataArchiveController::class)
         ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{archivedRecord}', 'show')->name('show');
            Route::patch('/{archivedRecord}/restore', 'restore')->name('restore');
            Route::delete('/{archivedRecord}/purge', 'purge')->name('purge');
            Route::get('/search', 'search')->name('search');
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
    // 6. RATE LIMITS
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
Route::middleware(['auth', 'active'])
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

// =============================================
// AUTHENTICATION ROUTES (Breeze handles these)
// =============================================
require __DIR__.'/auth.php';
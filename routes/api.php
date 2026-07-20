<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\ReleaseController;
use App\Http\Controllers\Api\Mobile\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// =============================================
// PUBLIC API ROUTES
// =============================================

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// =============================================
// MOBILE API ROUTES (For BHW React Native App)
// =============================================

Route::prefix('mobile')
    ->name('api.mobile.')
    ->group(function () {
        Route::middleware('dynamic-throttle:auth')
            ->prefix('auth')
            ->name('auth.')
            ->group(function () {
                Route::post('/login', [AuthController::class, 'login'])->name('login');
                Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            });

        Route::get('/release-check', [ReleaseController::class, 'show'])->name('release-check');

        Route::middleware(['auth:sanctum', 'mobile-token', 'dynamic-throttle:api-global'])
            ->group(function () {
                Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
                Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('bootstrap');
                Route::get('/seed', [SyncController::class, 'seed'])->name('seed');
                Route::post('/sync', [SyncController::class, 'sync'])->name('sync');
                Route::get('/verify', [SyncController::class, 'verify'])->name('verify');
                Route::post('/report-failure', [SyncController::class, 'reportFailure'])->name('report-failure');
            });
    });

// =============================================
// FALLBACK FOR UNDEFINED API ROUTES
// =============================================

Route::fallback(function () {
    return response()->json([
        'error' => 'API endpoint not found',
        'message' => 'The requested API endpoint does not exist.',
    ], 404);
});

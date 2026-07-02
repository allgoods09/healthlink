<?php

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

Route::middleware('auth:sanctum')
     ->prefix('mobile')
     ->name('api.mobile.')
     ->group(function () {

    // =============================================
    // SYNC ENDPOINTS
    // =============================================

    /**
     * Get seeded data for BHW's assigned purok
     * GET /api/mobile/seed
     */
    Route::get('/seed', function (Request $request) {
        $user = $request->user();
        
        // Only BHWs can access mobile seed
        if ($user->role !== 'bhw') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get all data for the BHW's assigned purok
        $purok = $user->assignedPurok()->with(['households.residents'])->first();

        return response()->json([
            'purok' => $purok,
            'barangay' => $user->assignedBarangay,
            'user' => $user,
            'seeded_at' => now()->toIso8601String(),
        ]);
    })->name('seed');

    /**
     * Sync data from mobile to server
     * POST /api/mobile/sync
     */
    Route::post('/sync', function (Request $request) {
        $user = $request->user();
        
        if ($user->role !== 'bhw') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate request
        $request->validate([
            'households' => ['nullable', 'array'],
            'residents' => ['nullable', 'array'],
            'device_name' => ['nullable', 'string'],
            'device_model' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
        ]);

        $startTime = microtime(true);
        $recordsSynced = 0;
        $syncMetadata = [];

        // Process households
        if ($request->has('households')) {
            foreach ($request->households as $householdData) {
                // Validate that household belongs to BHW's purok
                $household = \App\Models\Household::where('id', $householdData['id'])
                    ->where('purok_id', $user->assigned_purok_id)
                    ->first();

                if ($household) {
                    $household->update($householdData);
                    $recordsSynced++;
                }
            }
            $syncMetadata['households_synced'] = count($request->households);
        }

        // Process residents
        if ($request->has('residents')) {
            foreach ($request->residents as $residentData) {
                // Validate that resident belongs to BHW's purok
                $resident = \App\Models\Resident::where('id', $residentData['id'])
                    ->whereHas('household', function ($query) use ($user) {
                        $query->where('purok_id', $user->assigned_purok_id);
                    })
                    ->first();

                if ($resident) {
                    $resident->update($residentData);
                    $recordsSynced++;
                }
            }
            $syncMetadata['residents_synced'] = count($request->residents);
        }

        $duration = round((microtime(true) - $startTime) * 1000);

        // Log the sync
        \App\Models\SyncLog::create([
            'user_id' => $user->id,
            'device_name' => $request->device_name ?? 'Unknown',
            'device_model' => $request->device_model ?? 'Unknown',
            'app_version' => $request->app_version ?? 'Unknown',
            'records_synced' => $recordsSynced,
            'payload_size' => $request->header('Content-Length'),
            'sync_duration' => $duration,
            'status' => 'success',
            'ip_address' => $request->ip(),
            'network_type' => $request->header('X-Network-Type'),
            'sync_metadata' => $syncMetadata,
        ]);

        // Log to audit
        \App\Models\AuditLog::log([
            'user_id' => $user->id,
            'event_type' => 'synced',
            'event_description' => "Synced {$recordsSynced} records from mobile",
            'model_type' => \App\Models\SyncLog::class,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $syncMetadata,
        ]);

        return response()->json([
            'success' => true,
            'records_synced' => $recordsSynced,
            'duration_ms' => $duration,
            'synced_at' => now()->toIso8601String(),
        ]);
    })->name('sync');

    /**
     * Check if device token is still valid
     * GET /api/mobile/verify
     */
    Route::get('/verify', function (Request $request) {
        $user = $request->user();
        
        return response()->json([
            'valid' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'assigned_barangay_id' => $user->assigned_barangay_id,
                'assigned_purok_id' => $user->assigned_purok_id,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    })->name('verify');

    /**
     * Report sync failure from mobile
     * POST /api/mobile/report-failure
     */
    Route::post('/report-failure', function (Request $request) {
        $user = $request->user();
        
        $request->validate([
            'error_message' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'device_model' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
        ]);

        // Log the failure
        \App\Models\SyncLog::create([
            'user_id' => $user->id,
            'device_name' => $request->device_name ?? 'Unknown',
            'device_model' => $request->device_model ?? 'Unknown',
            'app_version' => $request->app_version ?? 'Unknown',
            'records_synced' => 0,
            'status' => 'failed',
            'error_message' => $request->error_message,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'reported' => true,
        ]);
    })->name('report-failure');
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
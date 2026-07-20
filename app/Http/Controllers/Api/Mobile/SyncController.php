<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Support\MobileBootstrapPayload;
use App\Support\MobileReleaseManager;
use App\Support\MobileSyncProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    /**
     * Download the full mobile bootstrap data for the assigned barangay.
     */
    public function bootstrap(Request $request, MobileBootstrapPayload $bootstrapPayload): JsonResponse
    {
        $settings = $this->releaseManager->releaseSettings();

        if (! $settings['sync_download_enabled']) {
            return response()->json([
                'message' => $settings['maintenance_message']
                    ?: 'Mobile data download is temporarily paused while HealthLink BHW updates are being prepared.',
                'maintenance' => $settings,
                'release' => $this->releaseManager->releasePayload(),
            ], 503);
        }

        return response()->json($bootstrapPayload->build($request->user()));
    }

    /**
     * Alias for legacy seed calls.
     */
    public function seed(Request $request, MobileBootstrapPayload $bootstrapPayload): JsonResponse
    {
        return $this->bootstrap($request, $bootstrapPayload);
    }

    /**
     * Sync mobile changes back to the server.
     */
    public function sync(Request $request, MobileSyncProcessor $processor): JsonResponse
    {
        $settings = $this->releaseManager->releaseSettings();

        if (! $settings['sync_upload_enabled']) {
            return response()->json([
                'message' => $settings['maintenance_message']
                    ?: 'Mobile upload sync is temporarily paused while HealthLink BHW updates are being prepared.',
                'maintenance' => $settings,
                'release' => $this->releaseManager->releasePayload(),
            ], 503);
        }

        $request->validate([
            'households' => ['nullable', 'array'],
            'residents' => ['nullable', 'array'],
            'field_visits' => ['nullable', 'array'],
            'device_name' => ['nullable', 'string'],
            'device_model' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
        ]);

        $totalRecords = count($request->input('households', []))
            + count($request->input('residents', []))
            + count($request->input('field_visits', []));

        $maxBatchSize = max((int) Setting::getValue('sync_batch_size', 100), 1);

        if ($totalRecords > $maxBatchSize) {
            return response()->json([
                'message' => 'The submitted sync payload exceeds the configured batch size.',
                'errors' => [
                    'sync' => [
                        "Only {$maxBatchSize} records can be synced per request.",
                    ],
                ],
                'submitted_records' => $totalRecords,
                'max_batch_size' => $maxBatchSize,
            ], 422);
        }

        $startTime = microtime(true);
        $syncResult = $processor->process($request->user(), [
            'households' => $request->input('households', []),
            'residents' => $request->input('residents', []),
            'field_visits' => $request->input('field_visits', []),
        ]);
        $duration = round((microtime(true) - $startTime) * 1000);

        $syncMetadata = array_merge($syncResult['summary'], [
            'submitted_records' => $totalRecords,
            'resolved_records' => $syncResult['resolved_records'],
        ]);

        $syncLog = SyncLog::create([
            'user_id' => $request->user()->id,
            'device_name' => $request->device_name ?? 'Unknown',
            'device_model' => $request->device_model ?? 'Unknown',
            'app_version' => $request->app_version ?? 'Unknown',
            'records_synced' => $syncResult['records_synced'],
            'payload_size' => $request->header('Content-Length'),
            'sync_duration' => $duration,
            'status' => $syncResult['status'],
            'error_message' => $syncResult['error_message'],
            'ip_address' => $request->ip(),
            'network_type' => $request->header('X-Network-Type'),
            'sync_metadata' => $syncMetadata,
        ]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'event_type' => 'synced',
            'event_description' => ucfirst($syncResult['status'])." mobile sync: {$syncResult['records_synced']} records accepted",
            'model_type' => SyncLog::class,
            'model_id' => $syncLog->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $syncMetadata,
        ]);

        return response()->json([
            'success' => $syncResult['status'] === SyncLog::STATUS_SUCCESS,
            'status' => $syncResult['status'],
            'records_synced' => $syncResult['records_synced'],
            'failed_records' => $syncResult['failures'],
            'resolved_records' => $syncResult['resolved_records'],
            'summary' => $syncResult['summary'],
            'duration_ms' => $duration,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Verify whether the current mobile token is still valid.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'valid' => true,
            'server_time' => now()->toIso8601String(),
            'maintenance' => $this->releaseManager->releaseSettings(),
            'release' => $this->releaseManager->releasePayload(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'assigned_barangay_id' => $user->assigned_barangay_id,
                'assigned_purok_id' => $user->assigned_purok_id,
            ],
        ]);
    }

    /**
     * Record a reported sync failure from the mobile client.
     */
    public function reportFailure(Request $request): JsonResponse
    {
        $request->validate([
            'error_message' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
            'device_model' => ['nullable', 'string'],
            'app_version' => ['nullable', 'string'],
        ]);

        SyncLog::create([
            'user_id' => $request->user()->id,
            'device_name' => $request->device_name ?? 'Unknown',
            'device_model' => $request->device_model ?? 'Unknown',
            'app_version' => $request->app_version ?? 'Unknown',
            'records_synced' => 0,
            'status' => SyncLog::STATUS_FAILED,
            'error_message' => $request->error_message,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'reported' => true,
        ]);
    }
}

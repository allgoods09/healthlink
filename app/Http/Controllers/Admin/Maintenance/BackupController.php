<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use App\Support\DatabaseBackupManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class BackupController extends Controller
{
    /**
     * Display a listing of backups.
     */
    public function index(Request $request, DatabaseBackupManager $backupManager)
    {
        Gate::authorize('viewAny', Backup::class);

        $query = Backup::query()->with('generator');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('backup_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('filename', 'LIKE', "%{$search}%");
        }

        $backups = $query->latest()
                         ->paginate(15)
                         ->withQueryString();

        $backupTypes = Backup::TYPES;
        $statuses = ['pending', 'in_progress', 'completed', 'failed'];
        $capabilities = $backupManager->capabilities();
        $summarySource = Backup::query()->get(['status', 'metadata']);
        $summary = [
            'total' => $summarySource->count(),
            'completed' => $summarySource->where('status', Backup::STATUS_COMPLETED)->count(),
            'failed' => $summarySource->where('status', Backup::STATUS_FAILED)->count(),
            'verified' => $summarySource->filter(fn (Backup $backup) => $backup->integrity_status === 'verified')->count(),
            'restored' => $summarySource->filter(fn (Backup $backup) => $backup->restore_count > 0)->count(),
        ];

        return view('admin.maintenance.backups.index', compact('backups', 'backupTypes', 'statuses', 'capabilities', 'summary'));
    }

    /**
     * Display a specific backup and its recovery history.
     */
    public function show(Backup $backup, DatabaseBackupManager $backupManager)
    {
        Gate::authorize('view', $backup);

        $backup->load('generator');
        $restoredBy = $backup->metadataValue('last_restored_by')
            ? User::find($backup->metadataValue('last_restored_by'))
            : null;

        return view('admin.maintenance.backups.show', [
            'backup' => $backup,
            'capabilities' => $backupManager->capabilities(),
            'restoredBy' => $restoredBy,
        ]);
    }

    /**
     * Generate a new backup.
     */
    public function generate(Request $request, DatabaseBackupManager $backupManager)
    {
        Gate::authorize('create', Backup::class);

        $request->validate([
            'backup_type' => ['required', 'in:full,schema_only,data_only'],
            'notes' => ['nullable', 'string'],
        ]);

        $backupType = $request->backup_type;
        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$backupType}_{$timestamp}.sql";
        $retentionDays = max((int) Setting::getValue('backup_retention_days', 30), 1);

        // Create backup record
        $backup = Backup::create([
            'filename' => $filename,
            'file_path' => "backups/{$filename}",
            'backup_type' => $backupType,
            'status' => 'pending',
            'generated_by' => Auth::id(),
            'storage_location' => 'local',
            'notes' => $request->notes,
            'metadata' => [
                'db_name' => config('database.connections.'.config('database.default').'.database'),
                'generated_at' => now()->toIso8601String(),
                'integrity_status' => 'unverified',
            ],
        ]);

        // Update status to in_progress
        $backup->update(['status' => 'in_progress']);

        // Log the backup start
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'backup_generated',
            'event_description' => "Started generating {$backupType} backup: {$filename}",
            'model_type' => Backup::class,
            'model_id' => $backup->id,
            'metadata' => ['backup_type' => $backupType],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        try {
            $result = $backupManager->generate($backup);

            $backup->update([
                'status' => Backup::STATUS_COMPLETED,
                'file_size' => $result['file_size'] ?? $backup->file_size,
                'expires_at' => now()->addDays($retentionDays),
                'metadata' => array_merge($backup->metadata ?? [], $result['metadata'] ?? []),
            ]);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_generated',
                'event_description' => "Successfully generated {$backupType} backup: {$filename}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'metadata' => [
                    'backup_type' => $backupType,
                    'file_size' => $backup->file_size,
                    'checksum_sha256' => $backup->checksum_sha256,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('success', "Backup {$filename} generated successfully.");
        } catch (\Throwable $e) {
            $backup->update([
                'status' => Backup::STATUS_FAILED,
                'metadata' => array_merge($backup->metadata ?? [], [
                    'generation_error' => $e->getMessage(),
                    'last_failed_at' => now()->toIso8601String(),
                ]),
                'expires_at' => now()->addDays($retentionDays),
            ]);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_generated',
                'event_description' => "Error generating {$backupType} backup: {$e->getMessage()}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'metadata' => [
                    'backup_type' => $backupType,
                    'error' => $e->getMessage(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', "Backup generation failed: {$e->getMessage()}");
        }
    }

    /**
     * Download a backup file.
     */
    public function download(Backup $backup)
    {
        Gate::authorize('view', $backup);

        if ($backup->status !== 'completed') {
            return redirect()
                ->back()
                ->with('error', 'This backup is not ready for download.');
        }

        $filePath = $backup->absolute_path;

        if (!file_exists($filePath)) {
            $backup->update([
                'metadata' => array_merge($backup->metadata ?? [], [
                    'integrity_status' => 'missing',
                    'integrity_checked_at' => now()->toIso8601String(),
                    'last_integrity_error' => 'Backup file is missing from local storage.',
                ]),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Backup file not found.');
        }

        // Log the download
        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'exported',
            'event_description' => "Downloaded backup: {$backup->filename}",
            'model_type' => Backup::class,
            'model_id' => $backup->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->download($filePath, $backup->filename);
    }

    /**
     * Verify a backup file against its recorded metadata.
     */
    public function verify(Backup $backup, DatabaseBackupManager $backupManager)
    {
        Gate::authorize('update', $backup);

        $result = $backupManager->verify($backup);

        $backup->update([
            'file_size' => $result['file_size'] ?? $backup->file_size,
            'metadata' => array_merge($backup->metadata ?? [], $result['metadata'] ?? []),
        ]);

        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => "Verified backup integrity for {$backup->filename}",
            'model_type' => Backup::class,
            'model_id' => $backup->id,
            'metadata' => [
                'integrity_status' => $backup->integrity_status,
                'checksum_sha256' => $backup->checksum_sha256,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.backups.show', $backup)
            ->with($result['valid'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Restore a completed backup after explicit confirmation.
     */
    public function restore(Request $request, Backup $backup, DatabaseBackupManager $backupManager)
    {
        Gate::authorize('restore', $backup);

        if (! $backup->is_restorable) {
            return redirect()
                ->back()
                ->with('error', 'Only completed backups with an available file can be restored.');
        }

        $validated = $request->validate([
            'confirmation_phrase' => ['required', 'in:RESTORE'],
            'action_reason' => ['required', 'string', 'max:500'],
        ], [
            'confirmation_phrase.in' => 'Type RESTORE exactly to continue with database restoration.',
        ]);

        try {
            $result = $backupManager->restore($backup, $validated['action_reason']);

            $backup->update([
                'metadata' => array_merge($backup->metadata ?? [], $result['metadata'] ?? [], [
                    'last_restored_by' => Auth::id(),
                    'last_restore_notes' => $validated['action_reason'],
                ]),
            ]);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_restored',
                'event_description' => "Restored backup {$backup->filename}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'metadata' => [
                    'confirmation_phrase' => $validated['confirmation_phrase'],
                    'action_reason' => $validated['action_reason'],
                    'restore_count' => $backup->restore_count,
                    'checksum_sha256' => $backup->checksum_sha256,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('success', $result['message'] ?? "Backup {$backup->filename} restored successfully.");
        } catch (\Throwable $e) {
            $backup->update([
                'metadata' => array_merge($backup->metadata ?? [], [
                    'last_restore_error' => $e->getMessage(),
                    'last_restore_failed_at' => now()->toIso8601String(),
                ]),
            ]);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_restored',
                'event_description' => "Failed restoring backup {$backup->filename}: {$e->getMessage()}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'metadata' => [
                    'error' => $e->getMessage(),
                    'confirmation_phrase' => $validated['confirmation_phrase'],
                    'action_reason' => $validated['action_reason'],
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.show', $backup)
                ->with('error', "Backup restore failed: {$e->getMessage()}");
        }
    }

    /**
     * Delete a backup file.
     */
    public function destroy(Backup $backup)
    {
        Gate::authorize('delete', $backup);

        $filename = $backup->filename;

        // Delete the file
        $filePath = $backup->absolute_path;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $backup->delete();

        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Deleted backup: {$filename}",
            'model_type' => Backup::class,
            'model_id' => $backup->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.backups.index')
            ->with('success', "Backup {$filename} deleted successfully.");
    }

    /**
     * Delete expired backups.
     */
    public function deleteExpired()
    {
        Gate::authorize('viewAny', Backup::class);

        $expired = Backup::where('expires_at', '<', now())
                         ->where('status', 'completed')
                         ->get();

        $count = 0;
        foreach ($expired as $backup) {
            $filePath = $backup->absolute_path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $backup->delete();
            $count++;
        }

        \App\Models\AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'deleted',
            'event_description' => "Deleted {$count} expired backups",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()
            ->route('admin.backups.index')
            ->with('success', "Deleted {$count} expired backups.");
    }
}

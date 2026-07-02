<?php

namespace App\Http\Controllers\Admin\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class BackupController extends Controller
{
    /**
     * Display a listing of backups.
     */
    public function index(Request $request)
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

        return view('admin.maintenance.backups.index', compact('backups', 'backupTypes', 'statuses'));
    }

    /**
     * Generate a new backup.
     */
    public function generate(Request $request)
    {
        Gate::authorize('create', Backup::class);

        $request->validate([
            'backup_type' => ['required', 'in:full,schema_only,data_only'],
            'notes' => ['nullable', 'string'],
        ]);

        $backupType = $request->backup_type;
        $timestamp = now()->format('Y-m-d_His');
        $filename = "backup_{$backupType}_{$timestamp}.sql";

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
                'db_name' => config('database.connections.mysql.database'),
                'generated_at' => now()->toIso8601String(),
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
            // Build the mysqldump command
            $dbConfig = config('database.connections.mysql');
            $dumpCommand = sprintf(
                'mysqldump --host=%s --port=%s --user=%s --password=%s %s %s > %s',
                escapeshellarg($dbConfig['host']),
                escapeshellarg($dbConfig['port'] ?? 3306),
                escapeshellarg($dbConfig['username']),
                escapeshellarg($dbConfig['password']),
                $backupType === 'schema_only' ? '--no-data' : '',
                $backupType === 'data_only' ? '--no-create-info' : '',
                escapeshellarg(storage_path("app/backups/{$filename}"))
            );

            // Execute the dump
            exec($dumpCommand, $output, $returnCode);

            if ($returnCode === 0) {
                // Get the file size
                $filePath = storage_path("app/backups/{$filename}");
                $fileSize = file_exists($filePath) ? filesize($filePath) : null;

                // Update backup record
                $backup->update([
                    'status' => 'completed',
                    'file_size' => $fileSize,
                    'expires_at' => now()->addDays(30),
                ]);

                \App\Models\AuditLog::log([
                    'user_id' => Auth::id(),
                    'event_type' => 'backup_generated',
                    'event_description' => "Successfully generated {$backupType} backup: {$filename}",
                    'model_type' => Backup::class,
                    'model_id' => $backup->id,
                    'metadata' => [
                        'backup_type' => $backupType,
                        'file_size' => $fileSize,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return redirect()
                    ->route('admin.backups.index')
                    ->with('success', "Backup {$filename} generated successfully.");
            }

            // Backup failed
            $backup->update(['status' => 'failed']);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_generated',
                'event_description' => "Failed to generate {$backupType} backup: {$filename}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', "Failed to generate backup. Please check the logs for details.");

        } catch (\Exception $e) {
            $backup->update(['status' => 'failed']);

            \App\Models\AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'backup_generated',
                'event_description' => "Error generating {$backupType} backup: {$e->getMessage()}",
                'model_type' => Backup::class,
                'model_id' => $backup->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', "Error generating backup: {$e->getMessage()}");
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

        $filePath = storage_path("app/{$backup->file_path}");

        if (!file_exists($filePath)) {
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
     * Delete a backup file.
     */
    public function destroy(Backup $backup)
    {
        Gate::authorize('delete', $backup);

        $filename = $backup->filename;

        // Delete the file
        $filePath = storage_path("app/{$backup->file_path}");
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
            $filePath = storage_path("app/{$backup->file_path}");
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
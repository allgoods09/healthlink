<?php

namespace App\Support;

use App\Models\MobileAppRelease;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class MobileReleaseManager
{
    public function currentPublishedRelease(): ?MobileAppRelease
    {
        return MobileAppRelease::query()
            ->forBhwAndroid()
            ->published()
            ->latest('published_at')
            ->latest('version_code')
            ->first();
    }

    public function releaseSettings(): array
    {
        return [
            'login_enabled' => $this->settingAsBoolean('mobile_bhw_login_enabled', true),
            'sync_upload_enabled' => $this->settingAsBoolean('mobile_bhw_sync_upload_enabled', true),
            'sync_download_enabled' => $this->settingAsBoolean('mobile_bhw_sync_download_enabled', true),
            'maintenance_message' => trim((string) Setting::getValue('mobile_bhw_maintenance_message', '')) ?: null,
        ];
    }

    public function hasDownloadableArtifact(?MobileAppRelease $release): bool
    {
        if (! $release) {
            return false;
        }

        return match ($release->artifact_source) {
            MobileAppRelease::SOURCE_URL => filled($release->artifact_url),
            default => filled($release->artifact_path) && Storage::disk('local')->exists($release->artifact_path),
        };
    }

    public function artifactStoragePath(?MobileAppRelease $release): ?string
    {
        if (! $release || $release->artifact_source !== MobileAppRelease::SOURCE_UPLOAD || ! $release->artifact_path) {
            return null;
        }

        return Storage::disk('local')->path($release->artifact_path);
    }

    public function publicDownloadUrl(): string
    {
        return route('mobile.bhw.download');
    }

    public function publicUpdatePageUrl(): string
    {
        return route('mobile.bhw.update');
    }

    public function releasePayload(?MobileAppRelease $release = null): ?array
    {
        $release ??= $this->currentPublishedRelease();

        if (! $release || ! $this->hasDownloadableArtifact($release)) {
            return null;
        }

        return [
            'id' => $release->id,
            'version_name' => $release->version_name,
            'version_code' => $release->version_code,
            'release_title' => $release->release_title,
            'release_notes' => $release->release_notes,
            'status' => $release->status,
            'status_label' => $release->status_label,
            'update_mode' => $release->update_mode,
            'update_mode_label' => $release->update_mode_label,
            'artifact_source' => $release->artifact_source,
            'artifact_source_label' => $release->artifact_source_label,
            'published_at' => $release->published_at?->toIso8601String(),
            'published_at_human' => $release->published_at?->format('F d, Y h:i A'),
            'download_url' => $this->publicDownloadUrl(),
            'update_page_url' => $this->publicUpdatePageUrl(),
        ];
    }

    public function releaseCheckPayload(?int $currentVersionCode = null): array
    {
        $release = $this->currentPublishedRelease();
        $payload = $this->releasePayload($release);
        $settings = $this->releaseSettings();

        $updateAvailable = $payload && $currentVersionCode !== null && $payload['version_code'] > $currentVersionCode;
        $requiredUpdate = $updateAvailable && $payload['update_mode'] === MobileAppRelease::UPDATE_REQUIRED;

        return [
            'scope' => MobileAppRelease::APP_SCOPE_BHW,
            'platform' => MobileAppRelease::PLATFORM_ANDROID,
            'checked_at' => now()->toIso8601String(),
            'release' => $payload,
            'update' => [
                'available' => (bool) $updateAvailable,
                'required' => (bool) $requiredUpdate,
                'can_continue_offline' => true,
                'message' => $updateAvailable
                    ? ($requiredUpdate
                        ? 'A required HealthLink BHW update is ready. You may continue using offline records, but syncing should wait until you update.'
                        : 'A newer HealthLink BHW release is now available.')
                    : null,
            ],
            'maintenance' => $settings,
        ];
    }

    private function settingAsBoolean(string $key, bool $default): bool
    {
        $value = Setting::getValue($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }
}

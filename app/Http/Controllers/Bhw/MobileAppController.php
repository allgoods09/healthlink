<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Controller;
use App\Models\MobileAppRelease;
use App\Support\MobileReleaseManager;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileAppController extends Controller
{
    private const APK_FILENAME = 'healthlink-bhw-android.apk';

    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    public function show(): View
    {
        $release = $this->releaseManager->currentPublishedRelease();
        $artifact = $this->apkArtifact();
        $externalUrl = $this->externalApkUrl();
        $releasePayload = $this->releaseManager->releasePayload($release);
        $releaseHasArtifact = $this->releaseManager->hasDownloadableArtifact($release);
        $legacyHostedPreferred = ! $releaseHasArtifact && ! is_null($externalUrl);
        $apkSizeLabel = $legacyHostedPreferred
            ? null
            : ($release && $releaseHasArtifact && $release->artifact_source === MobileAppRelease::SOURCE_UPLOAD
                ? $this->humanReadableBytes(File::size($this->releaseManager->artifactStoragePath($release)))
                : ($artifact ? $this->humanReadableBytes(File::size($artifact)) : null));
        $apkUpdatedAt = $legacyHostedPreferred
            ? null
            : ($release?->published_at
                ?? ($artifact ? Carbon::createFromTimestamp(File::lastModified($artifact)) : null));
        $apkSourceLabel = $release && $releaseHasArtifact
            ? $release->artifact_source_label
            : ($legacyHostedPreferred
                ? 'Hosted from the configured mobile release link'
                : ($artifact
                    ? 'Hosted on this HealthLink server'
                    : ($externalUrl ? 'Hosted from the configured mobile release link' : null)));

        return view('bhw.mobile-app.show', [
            'release' => $release,
            'releasePayload' => $releasePayload,
            'apkAvailable' => $releaseHasArtifact || ! is_null($artifact) || ! is_null($externalUrl),
            'apkName' => $release?->download_filename ?? self::APK_FILENAME,
            'apkSizeLabel' => $apkSizeLabel,
            'apkUpdatedAt' => $apkUpdatedAt,
            'apkExternalUrl' => $release && $releaseHasArtifact && $release->artifact_source === MobileAppRelease::SOURCE_URL
                ? $release->artifact_url
                : $externalUrl,
            'apkSourceLabel' => $apkSourceLabel,
            'publicUpdateUrl' => $this->releaseManager->publicUpdatePageUrl(),
        ]);
    }

    public function download(): BinaryFileResponse|RedirectResponse
    {
        $release = $this->releaseManager->currentPublishedRelease();

        if ($this->releaseManager->hasDownloadableArtifact($release)) {
            if ($release?->artifact_source === MobileAppRelease::SOURCE_URL) {
                return redirect()->away((string) $release->artifact_url);
            }

            return response()->download(
                $this->releaseManager->artifactStoragePath($release),
                $release->download_filename
            );
        }

        $artifact = $this->apkArtifact();
        $externalUrl = $this->externalApkUrl();

        if ($externalUrl) {
            return redirect()->away($externalUrl);
        }

        if (! $artifact) {
            return back()->with('error', 'The Android APK is not available on this server yet. Please contact your administrator.');
        }

        return response()->download($artifact, self::APK_FILENAME);
    }

    private function apkArtifact(): ?string
    {
        $path = storage_path('app/mobile-builds/'.self::APK_FILENAME);

        return File::exists($path) ? $path : null;
    }

    private function externalApkUrl(): ?string
    {
        $url = trim((string) Config::get('app.bhw_mobile_apk_url', ''));

        return $url !== '' ? $url : null;
    }

    private function humanReadableBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}

<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\MobileReleaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BhwReleaseController extends Controller
{
    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    public function show(): View
    {
        $release = $this->releaseManager->currentPublishedRelease();

        return view('mobile.bhw.update', [
            'release' => $release,
            'releasePayload' => $this->releaseManager->releasePayload($release),
            'mobileSettings' => $this->releaseManager->releaseSettings(),
            'downloadAvailable' => $this->releaseManager->hasDownloadableArtifact($release),
        ]);
    }

    public function download(): BinaryFileResponse|RedirectResponse
    {
        $release = $this->releaseManager->currentPublishedRelease();

        if (! $this->releaseManager->hasDownloadableArtifact($release)) {
            return back()->with('error', 'No downloadable HealthLink BHW APK is published yet.');
        }

        if ($release->artifact_source === \App\Models\MobileAppRelease::SOURCE_URL) {
            return redirect()->away((string) $release->artifact_url);
        }

        return response()->download(
            $this->releaseManager->artifactStoragePath($release),
            $release->download_filename
        );
    }
}

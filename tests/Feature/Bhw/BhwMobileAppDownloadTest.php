<?php

namespace Tests\Feature\Bhw;

use App\Models\Barangay;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BhwMobileAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactPath;

    private bool $artifactOriginallyPresent = false;

    private ?string $artifactOriginalContents = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactPath = storage_path('app/mobile-builds/healthlink-bhw-android.apk');
        $this->artifactOriginallyPresent = File::exists($this->artifactPath);
        $this->artifactOriginalContents = $this->artifactOriginallyPresent
            ? File::get($this->artifactPath)
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->artifactOriginallyPresent) {
            File::ensureDirectoryExists(dirname($this->artifactPath));
            File::put($this->artifactPath, $this->artifactOriginalContents ?? '');
        } elseif (File::exists($this->artifactPath)) {
            File::delete($this->artifactPath);

            $directory = dirname($this->artifactPath);

            if (File::isDirectory($directory)
                && count(File::files($directory)) === 0
                && count(File::directories($directory)) === 0) {
                File::deleteDirectory($directory);
            }
        }

        parent::tearDown();
    }

    public function test_verified_bhw_can_open_the_mobile_app_download_page(): void
    {
        [$bhw] = $this->bhwContext();
        $this->ensureArtifactMissing();

        $response = $this->actingAs($bhw)->get(route('bhw.mobile-app.show'));

        $response->assertOk();
        $response->assertSee('Download App');
        $response->assertSee('HealthLink Mobile');
        $response->assertSee('No packaged Android APK has been uploaded', false);
    }

    public function test_download_route_redirects_back_with_error_when_apk_is_missing(): void
    {
        [$bhw] = $this->bhwContext();
        $this->ensureArtifactMissing();

        $response = $this->actingAs($bhw)
            ->from(route('bhw.mobile-app.show'))
            ->get(route('bhw.mobile-app.download'));

        $response->assertRedirect(route('bhw.mobile-app.show'));
        $response->assertSessionHas('error', 'The Android APK is not available on this server yet. Please contact your administrator.');
    }

    public function test_verified_bhw_can_download_the_android_apk_when_it_is_available(): void
    {
        [$bhw] = $this->bhwContext();

        File::ensureDirectoryExists(dirname($this->artifactPath));
        File::put($this->artifactPath, 'mock-apk-binary');

        $response = $this->actingAs($bhw)->get(route('bhw.mobile-app.download'));

        $response->assertOk();
        $response->assertDownload('healthlink-bhw-android.apk');
    }

    public function test_download_route_can_redirect_to_a_configured_hosted_apk_release(): void
    {
        [$bhw] = $this->bhwContext();
        $this->ensureArtifactMissing();

        config()->set('app.bhw_mobile_apk_url', 'https://example.com/healthlink-bhw-android.apk');

        $response = $this->actingAs($bhw)->get(route('bhw.mobile-app.download'));

        $response->assertRedirect('https://example.com/healthlink-bhw-android.apk');
    }

    private function bhwContext(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create([
            'barangay_id' => $barangay->id,
            'purok_number' => 2,
        ]);
        $bhw = User::factory()->create([
            'role' => 'bhw',
            'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id,
            'approval_status' => User::APPROVAL_APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return [$bhw, $barangay, $purok];
    }

    private function ensureArtifactMissing(): void
    {
        if (File::exists($this->artifactPath)) {
            File::delete($this->artifactPath);
        }
    }
}

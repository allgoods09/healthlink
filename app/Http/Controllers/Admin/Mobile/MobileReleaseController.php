<?php

namespace App\Http\Controllers\Admin\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MobileAppRelease;
use App\Models\Setting;
use App\Support\MobileReleaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileReleaseController extends Controller
{
    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', Setting::class);

        $currentRelease = $this->releaseManager->currentPublishedRelease();
        $releases = MobileAppRelease::query()
            ->forBhwAndroid()
            ->with(['createdBy', 'publishedBy', 'rolledBackFrom'])
            ->orderByRaw("case when status = 'published' then 0 when status = 'draft' then 1 else 2 end")
            ->orderByDesc('published_at')
            ->orderByDesc('version_code')
            ->get();

        return view('admin.mobile.releases.index', [
            'currentRelease' => $currentRelease,
            'releases' => $releases,
            'mobileSettings' => $this->releaseManager->releaseSettings(),
            'publicUpdateUrl' => $this->releaseManager->publicUpdatePageUrl(),
            'publicDownloadUrl' => $this->releaseManager->publicDownloadUrl(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Setting::class);

        return view('admin.mobile.releases.create', [
            'release' => new MobileAppRelease([
                'app_scope' => MobileAppRelease::APP_SCOPE_BHW,
                'platform' => MobileAppRelease::PLATFORM_ANDROID,
                'artifact_source' => MobileAppRelease::SOURCE_UPLOAD,
                'update_mode' => MobileAppRelease::UPDATE_OPTIONAL,
                'status' => MobileAppRelease::STATUS_DRAFT,
            ]),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Setting::class);

        $validated = $this->validateRelease($request);

        $release = new MobileAppRelease($validated);
        $release->app_scope = MobileAppRelease::APP_SCOPE_BHW;
        $release->platform = MobileAppRelease::PLATFORM_ANDROID;
        $release->created_by_user_id = Auth::id();
        $release->status = MobileAppRelease::STATUS_DRAFT;
        $release->save();

        $this->handleArtifactUpload($request, $release);

        if ($request->boolean('publish_now')) {
            $this->publishRelease($release);

            return redirect()
                ->route('admin.mobile-releases.show', $release)
                ->with('success', "Mobile release {$release->version_name} was published successfully.");
        }

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'created',
            'event_description' => "Created mobile release {$release->version_name}",
            'model_type' => MobileAppRelease::class,
            'model_id' => $release->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'version_name' => $release->version_name,
                'version_code' => $release->version_code,
                'status' => $release->status,
            ],
        ]);

        return redirect()
            ->route('admin.mobile-releases.show', $release)
            ->with('success', "Mobile release {$release->version_name} was saved as a draft.");
    }

    public function show(MobileAppRelease $mobileRelease): View
    {
        Gate::authorize('viewAny', Setting::class);

        $mobileRelease->load(['createdBy', 'publishedBy', 'rolledBackFrom']);

        return view('admin.mobile.releases.show', [
            'release' => $mobileRelease,
            'isCurrentRelease' => optional($this->releaseManager->currentPublishedRelease())->is($mobileRelease),
            'canDownloadArtifact' => $this->releaseManager->hasDownloadableArtifact($mobileRelease),
            'publicUpdateUrl' => $this->releaseManager->publicUpdatePageUrl(),
        ]);
    }

    public function edit(MobileAppRelease $mobileRelease): View
    {
        Gate::authorize('update', Setting::class);

        return view('admin.mobile.releases.edit', [
            'release' => $mobileRelease,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, MobileAppRelease $mobileRelease): RedirectResponse
    {
        Gate::authorize('update', Setting::class);

        $validated = $this->validateRelease($request, $mobileRelease);

        $oldValues = $mobileRelease->only([
            'version_name',
            'version_code',
            'release_title',
            'artifact_source',
            'artifact_url',
            'update_mode',
        ]);

        $mobileRelease->fill($validated);
        $mobileRelease->save();

        $this->handleArtifactUpload($request, $mobileRelease);

        if ($request->boolean('publish_now')) {
            $this->publishRelease($mobileRelease);

            return redirect()
                ->route('admin.mobile-releases.show', $mobileRelease)
                ->with('success', "Mobile release {$mobileRelease->version_name} was updated and published.");
        }

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => "Updated mobile release {$mobileRelease->version_name}",
            'model_type' => MobileAppRelease::class,
            'model_id' => $mobileRelease->id,
            'old_values' => $oldValues,
            'new_values' => $mobileRelease->only(array_keys($oldValues)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('admin.mobile-releases.show', $mobileRelease)
            ->with('success', "Mobile release {$mobileRelease->version_name} was updated.");
    }

    public function publish(Request $request, MobileAppRelease $mobileRelease): RedirectResponse
    {
        Gate::authorize('update', Setting::class);

        $this->publishRelease($mobileRelease);

        return back()->with(
            'success',
            "HealthLink BHW {$mobileRelease->version_name} is now the live downloadable release."
        );
    }

    public function retire(Request $request, MobileAppRelease $mobileRelease): RedirectResponse
    {
        Gate::authorize('update', Setting::class);

        if ($mobileRelease->status !== MobileAppRelease::STATUS_RETIRED) {
            $mobileRelease->update(['status' => MobileAppRelease::STATUS_RETIRED]);

            AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'updated',
                'event_description' => "Retired mobile release {$mobileRelease->version_name}",
                'model_type' => MobileAppRelease::class,
                'model_id' => $mobileRelease->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return back()->with('success', "Mobile release {$mobileRelease->version_name} was retired.");
    }

    public function download(MobileAppRelease $mobileRelease): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('viewAny', Setting::class);

        if (! $this->releaseManager->hasDownloadableArtifact($mobileRelease)) {
            return back()->with('error', 'This release does not currently have a downloadable APK attached.');
        }

        if ($mobileRelease->artifact_source === MobileAppRelease::SOURCE_URL) {
            return redirect()->away((string) $mobileRelease->artifact_url);
        }

        return response()->download(
            $this->releaseManager->artifactStoragePath($mobileRelease),
            $mobileRelease->download_filename
        );
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize('update', Setting::class);

        $validated = $request->validate([
            'login_enabled' => ['nullable', 'boolean'],
            'sync_upload_enabled' => ['nullable', 'boolean'],
            'sync_download_enabled' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = [
            'mobile_bhw_login_enabled' => $request->boolean('login_enabled'),
            'mobile_bhw_sync_upload_enabled' => $request->boolean('sync_upload_enabled'),
            'mobile_bhw_sync_download_enabled' => $request->boolean('sync_download_enabled'),
            'mobile_bhw_maintenance_message' => trim((string) ($validated['maintenance_message'] ?? '')),
        ];

        foreach ($settings as $key => $value) {
            Setting::setValue($key, is_bool($value) ? ($value ? '1' : '0') : $value, [
                'group' => 'mobile',
            ]);
        }

        AuditLog::log([
            'user_id' => Auth::id(),
            'event_type' => 'updated',
            'event_description' => 'Updated mobile release access controls',
            'model_type' => Setting::class,
            'model_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $settings,
        ]);

        return back()->with('success', 'Mobile access controls were updated successfully.');
    }

    private function validateRelease(Request $request, ?MobileAppRelease $mobileRelease = null): array
    {
        $validated = $request->validate([
            'version_name' => ['required', 'string', 'max:50'],
            'version_code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mobile_app_releases', 'version_code')
                    ->where(fn ($query) => $query
                        ->where('app_scope', MobileAppRelease::APP_SCOPE_BHW)
                        ->where('platform', MobileAppRelease::PLATFORM_ANDROID))
                    ->ignore($mobileRelease?->id),
            ],
            'release_title' => ['nullable', 'string', 'max:255'],
            'release_notes' => ['nullable', 'string'],
            'artifact_source' => ['required', Rule::in([
                MobileAppRelease::SOURCE_UPLOAD,
                MobileAppRelease::SOURCE_URL,
            ])],
            'artifact_url' => ['nullable', 'url', 'max:2000'],
            'artifact_file' => ['nullable', 'file', 'max:512000'],
            'update_mode' => ['required', Rule::in([
                MobileAppRelease::UPDATE_OPTIONAL,
                MobileAppRelease::UPDATE_REQUIRED,
            ])],
            'publish_now' => ['nullable', 'boolean'],
        ]);

        if ($validated['artifact_source'] === MobileAppRelease::SOURCE_UPLOAD) {
            $hasExistingUpload = $mobileRelease?->artifact_source === MobileAppRelease::SOURCE_UPLOAD
                && filled($mobileRelease?->artifact_path);

            if (! $request->hasFile('artifact_file') && ! $hasExistingUpload) {
                $request->validate([
                    'artifact_file' => ['required'],
                ], [
                    'artifact_file.required' => 'Please upload the Android APK file for this release.',
                ]);
            }

            $originalName = strtolower((string) $request->file('artifact_file')?->getClientOriginalName());
            if ($request->hasFile('artifact_file') && ! str_ends_with($originalName, '.apk')) {
                $request->validate([
                    'artifact_file' => ['prohibited'],
                ], [
                    'artifact_file.prohibited' => 'The uploaded mobile package must be an APK file.',
                ]);
            }

            $validated['artifact_url'] = null;
        }

        if ($validated['artifact_source'] === MobileAppRelease::SOURCE_URL && blank($validated['artifact_url'] ?? null)) {
            $request->validate([
                'artifact_url' => ['required'],
            ], [
                'artifact_url.required' => 'Please provide the hosted APK or release link.',
            ]);
        }

        return $validated;
    }

    private function handleArtifactUpload(Request $request, MobileAppRelease $mobileRelease): void
    {
        if ($mobileRelease->artifact_source === MobileAppRelease::SOURCE_URL) {
            if ($mobileRelease->artifact_path) {
                Storage::disk('local')->delete($mobileRelease->artifact_path);
            }

            $mobileRelease->forceFill(['artifact_path' => null])->save();

            return;
        }

        if (! $request->hasFile('artifact_file')) {
            return;
        }

        if ($mobileRelease->artifact_path) {
            Storage::disk('local')->delete($mobileRelease->artifact_path);
        }

        $storedPath = $request->file('artifact_file')->storeAs(
            'mobile-builds/releases',
            sprintf(
                'healthlink-bhw-v%s-%s.apk',
                $mobileRelease->version_code,
                now()->format('YmdHis')
            ),
            'local'
        );

        $mobileRelease->forceFill([
            'artifact_path' => $storedPath,
            'artifact_url' => null,
        ])->save();
    }

    private function publishRelease(MobileAppRelease $mobileRelease): void
    {
        if (! $this->releaseManager->hasDownloadableArtifact($mobileRelease)) {
            abort(422, 'This release cannot be published until it has a valid APK upload or hosted release link.');
        }

        DB::transaction(function () use ($mobileRelease): void {
            $currentRelease = $this->releaseManager->currentPublishedRelease();

            MobileAppRelease::query()
                ->forBhwAndroid()
                ->published()
                ->whereKeyNot($mobileRelease->id)
                ->update(['status' => MobileAppRelease::STATUS_RETIRED]);

            $mobileRelease->update([
                'status' => MobileAppRelease::STATUS_PUBLISHED,
                'published_at' => now(),
                'published_by_user_id' => Auth::id(),
                'rolled_back_from_release_id' => $currentRelease && $currentRelease->version_code > $mobileRelease->version_code
                    ? $currentRelease->id
                    : null,
            ]);

            AuditLog::log([
                'user_id' => Auth::id(),
                'event_type' => 'updated',
                'event_description' => "Published mobile release {$mobileRelease->version_name}",
                'model_type' => MobileAppRelease::class,
                'model_id' => $mobileRelease->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'version_name' => $mobileRelease->version_name,
                    'version_code' => $mobileRelease->version_code,
                    'previous_release_id' => $currentRelease?->id,
                    'rollback' => $currentRelease && $currentRelease->version_code > $mobileRelease->version_code,
                ],
            ]);
        });
    }
}

@php
    $selectedSource = old('artifact_source', $release->artifact_source ?? \App\Models\MobileAppRelease::SOURCE_UPLOAD);
    $selectedUpdateMode = old('update_mode', $release->update_mode ?? \App\Models\MobileAppRelease::UPDATE_OPTIONAL);
@endphp

<div x-data="{ artifactSource: @js($selectedSource) }" class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="version_name" class="block text-sm font-medium text-slate-700">Version name</label>
                <input
                    type="text"
                    name="version_name"
                    id="version_name"
                    value="{{ old('version_name', $release->version_name) }}"
                    placeholder="1.0.1"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                    required
                >
                @error('version_name')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="version_code" class="block text-sm font-medium text-slate-700">Version code</label>
                <input
                    type="number"
                    name="version_code"
                    id="version_code"
                    min="1"
                    value="{{ old('version_code', $release->version_code) }}"
                    placeholder="2"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                    required
                >
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Increase this every time you build a new APK so the app can detect newer releases correctly.
                </p>
                @error('version_code')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="release_title" class="block text-sm font-medium text-slate-700">Release title</label>
                <input
                    type="text"
                    name="release_title"
                    id="release_title"
                    value="{{ old('release_title', $release->release_title) }}"
                    placeholder="Field stability update"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                >
                @error('release_title')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="release_notes" class="block text-sm font-medium text-slate-700">Release notes</label>
                <textarea
                    name="release_notes"
                    id="release_notes"
                    rows="7"
                    placeholder="Explain what changed, what BHWs should expect, and whether sync behavior was updated."
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                >{{ old('release_notes', $release->release_notes) }}</textarea>
                @error('release_notes')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label for="artifact_source" class="block text-sm font-medium text-slate-700">APK source</label>
                <select
                    name="artifact_source"
                    id="artifact_source"
                    x-model="artifactSource"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                >
                    <option value="{{ \App\Models\MobileAppRelease::SOURCE_UPLOAD }}">Upload APK file</option>
                    <option value="{{ \App\Models\MobileAppRelease::SOURCE_URL }}">Hosted APK link</option>
                </select>
                @error('artifact_source')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="update_mode" class="block text-sm font-medium text-slate-700">Update behavior</label>
                <select
                    name="update_mode"
                    id="update_mode"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                >
                    <option value="{{ \App\Models\MobileAppRelease::UPDATE_OPTIONAL }}" @selected($selectedUpdateMode === \App\Models\MobileAppRelease::UPDATE_OPTIONAL)>
                        Optional update
                    </option>
                    <option value="{{ \App\Models\MobileAppRelease::UPDATE_REQUIRED }}" @selected($selectedUpdateMode === \App\Models\MobileAppRelease::UPDATE_REQUIRED)>
                        Required update
                    </option>
                </select>
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Required updates still allow offline use, but the mobile app will clearly tell BHWs to update before syncing again.
                </p>
                @error('update_mode')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-6 space-y-5">
            <div x-show="artifactSource === '{{ \App\Models\MobileAppRelease::SOURCE_UPLOAD }}'" x-cloak>
                <label for="artifact_file" class="block text-sm font-medium text-slate-700">Android APK file</label>
                <input
                    type="file"
                    name="artifact_file"
                    id="artifact_file"
                    accept=".apk,application/vnd.android.package-archive"
                    class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-tubigon file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-tubigon-hover"
                >
                @if($isEdit && $release->artifact_source === \App\Models\MobileAppRelease::SOURCE_UPLOAD && $release->artifact_path)
                    <p class="mt-2 text-xs text-slate-500">
                        Leave this blank to keep the currently attached APK. Uploading a new file will replace the previous package.
                    </p>
                @endif
                @error('artifact_file')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="artifactSource === '{{ \App\Models\MobileAppRelease::SOURCE_URL }}'" x-cloak>
                <label for="artifact_url" class="block text-sm font-medium text-slate-700">Hosted APK URL</label>
                <input
                    type="url"
                    name="artifact_url"
                    id="artifact_url"
                    value="{{ old('artifact_url', $release->artifact_url) }}"
                    placeholder="https://example.com/healthlink-bhw.apk"
                    class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"
                >
                <p class="mt-2 text-xs leading-5 text-slate-500">
                    Use this only if the APK is already hosted elsewhere and you want HealthLink to publish that link instead of storing the file locally.
                </p>
                @error('artifact_url')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="flex items-start gap-3">
            <input
                type="checkbox"
                name="publish_now"
                value="1"
                @checked(old('publish_now'))
                class="mt-1 rounded border-slate-300 text-tubigon focus:ring-tubigon"
            >
            <span>
                <span class="block text-sm font-medium text-slate-700">Publish immediately after saving</span>
                <span class="mt-1 block text-xs leading-5 text-slate-500">
                    Leave this unchecked if you want to save the release as a draft first, review it, and only publish once the admin team is ready.
                </span>
            </span>
        </label>
    </div>
</div>

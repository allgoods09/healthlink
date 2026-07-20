@extends('layouts.admin')

@section('title', 'Mobile Release Center - HealthLink Admin')
@section('header', 'Mobile Release Center')

@section('actions')
    <a href="{{ route('admin.mobile-releases.create') }}" class="inline-flex items-center rounded-md bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        New Release Draft
    </a>
@endsection

@section('content')
    <div class="mb-6 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon">Current Live Build</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ $currentRelease?->display_title ?? 'No published BHW release yet' }}
                    </h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Admins publish a finished APK here after building it externally. The mobile app checks this release center on app open and can warn BHWs when a newer build is available.
                    </p>
                </div>
                @if($currentRelease)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">
                        Version {{ $currentRelease->version_name }}
                    </span>
                @endif
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Public Update Page</p>
                    <a href="{{ $publicUpdateUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm font-semibold text-tubigon hover:text-tubigon-hover">
                        Open update page
                    </a>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Public APK Download</p>
                    <a href="{{ $publicDownloadUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-sm font-semibold text-tubigon hover:text-tubigon-hover">
                        Open download link
                    </a>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm text-slate-500">Current behavior</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">
                        {{ $currentRelease?->update_mode_label ?? 'Waiting for first published release' }}
                    </p>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                <p class="text-sm font-semibold text-blue-900">Safer publishing workflow</p>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-blue-900">
                    <li>Update the mobile codebase and bump the app version and version code.</li>
                    <li>Build the Android APK outside the server using Expo EAS.</li>
                    <li>Test the APK on an actual BHW device.</li>
                    <li>Create a draft here, upload the APK or paste the hosted link, and review the release notes.</li>
                    <li>Publish only after the build is confirmed working.</li>
                </ol>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Mobile Access Controls</h3>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                These controls act immediately on the BHW mobile app. Offline records already stored on the device remain usable even if a new update is waiting.
            </p>

            <form method="POST" action="{{ route('admin.mobile-releases.settings.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="login_enabled" value="1" @checked($mobileSettings['login_enabled']) class="mt-1 rounded border-slate-300 text-tubigon focus:ring-tubigon">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Allow mobile sign-in</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">Disable this if you need to pause fresh mobile logins during a controlled rollout.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="sync_upload_enabled" value="1" @checked($mobileSettings['sync_upload_enabled']) class="mt-1 rounded border-slate-300 text-tubigon focus:ring-tubigon">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Allow mobile upload sync</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">Turn this off if server-side data intake must be paused while investigating a mobile issue.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="sync_download_enabled" value="1" @checked($mobileSettings['sync_download_enabled']) class="mt-1 rounded border-slate-300 text-tubigon focus:ring-tubigon">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Allow initial/download sync</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">Turn this off if you need to freeze new dataset downloads while a replacement build is being prepared.</span>
                    </span>
                </label>

                <div>
                    <label for="maintenance_message" class="block text-sm font-medium text-slate-700">Maintenance message</label>
                    <textarea name="maintenance_message" id="maintenance_message" rows="5" class="mt-2 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="Example: A new BHW release is being prepared. Please continue using offline mode until the update is published.">{{ old('maintenance_message', $mobileSettings['maintenance_message']) }}</textarea>
                </div>

                <button type="submit" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Save Mobile Controls
                </button>
            </form>
        </section>
    </div>

    <section class="overflow-hidden rounded-3xl bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h3 class="text-lg font-semibold text-slate-900">Release History</h3>
            <p class="mt-2 text-sm text-slate-600">
                Every past release stays visible here so the admin team can inspect, download, or roll back to a known-good APK when necessary.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Published</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($releases as $release)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <p class="text-sm font-semibold text-slate-900">{{ $release->display_title }}</p>
                                <p class="mt-1 text-sm text-slate-500">Code {{ number_format($release->version_code) }}</p>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em]
                                    {{ $release->status === \App\Models\MobileAppRelease::STATUS_PUBLISHED ? 'bg-emerald-100 text-emerald-800' : ($release->status === \App\Models\MobileAppRelease::STATUS_DRAFT ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $release->status_label }}
                                </span>
                                <p class="mt-2 text-xs text-slate-500">{{ $release->update_mode_label }}</p>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-slate-600">
                                {{ $release->artifact_source_label }}
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-slate-600">
                                {{ $release->published_at?->format('F d, Y h:i A') ?? 'Not yet published' }}
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.mobile-releases.show', $release) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        View
                                    </a>
                                    <a href="{{ route('admin.mobile-releases.edit', $release) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        Edit
                                    </a>
                                    @if($release->status !== \App\Models\MobileAppRelease::STATUS_PUBLISHED)
                                        <form method="POST" action="{{ route('admin.mobile-releases.publish', $release) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-md bg-tubigon px-3 py-1.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                                                {{ $release->status === \App\Models\MobileAppRelease::STATUS_RETIRED ? 'Rollback' : 'Publish' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($release->status !== \App\Models\MobileAppRelease::STATUS_RETIRED)
                                        <form method="POST" action="{{ route('admin.mobile-releases.retire', $release) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center rounded-md bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">
                                                Retire
                                            </button>
                                        </form>
                                    @endif
                                    @if($release->artifact_source === \App\Models\MobileAppRelease::SOURCE_UPLOAD || $release->artifact_source === \App\Models\MobileAppRelease::SOURCE_URL)
                                        <a href="{{ route('admin.mobile-releases.download', $release) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                                            Download
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">
                                No BHW mobile releases have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@extends('layouts.portal')

@section('title', 'Download BHW Mobile App - HealthLink')
@section('header', 'Download App')
@section('subheader', 'Install the Android field companion used for offline household visits, resident lookup, and manual sync back to HealthLink.')

@section('actions')
    @if($apkAvailable)
        <a href="{{ route('bhw.mobile-app.download') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
            Download APK
        </a>
    @endif
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-[28px] bg-gradient-to-br from-tubigon to-tubigon-hover px-6 py-8 text-white shadow-xl shadow-tubigon/20">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/70">HealthLink Mobile</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">BHW field work, even offline</h2>
            <p class="mt-4 max-w-2xl text-sm leading-7 text-white/85">
                The mobile app gives Barangay Health Workers a simpler Android workspace for field visits, barangay-wide resident lookup, assigned-purok draft work, visit history, and safe manual syncing back to the HealthLink server.
            </p>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Primary Use</p>
                    <p class="mt-2 text-lg font-semibold">Offline-first field visits</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Platform</p>
                    <p class="mt-2 text-lg font-semibold">Android only</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Scope</p>
                    <p class="mt-2 text-lg font-semibold">Whole-barangay lookup, assigned-purok write access</p>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-4">
                    <p class="text-sm text-white/70">Sync Model</p>
                    <p class="mt-2 text-lg font-semibold">Manual upload first, then refresh</p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Build Status</p>
                @if($apkAvailable)
                    <p class="mt-3 text-lg font-semibold text-slate-900">{{ $apkName }}</p>
                    <div class="mt-4 space-y-2 text-sm text-slate-600">
                        @if($release)
                            <p>Published release: <span class="font-medium text-slate-900">{{ $release->version_name }}</span></p>
                            <p>Update behavior: <span class="font-medium text-slate-900">{{ $release->update_mode_label }}</span></p>
                        @endif
                        <p>Release source: <span class="font-medium text-slate-900">{{ $apkSourceLabel }}</span></p>
                        @if($apkSizeLabel)
                            <p>Package size: <span class="font-medium text-slate-900">{{ $apkSizeLabel }}</span></p>
                        @endif
                        @if($apkUpdatedAt)
                            <p>Last updated: <span class="font-medium text-slate-900">{{ $apkUpdatedAt->format('F d, Y h:i A') }}</span></p>
                        @endif
                    </div>
                    <a href="{{ route('bhw.mobile-app.download') }}" class="mt-5 inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white transition hover:bg-tubigon-hover">
                        Download Android APK
                    </a>
                    <a href="{{ $publicUpdateUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:border-tubigon/25 hover:text-tubigon">
                        View Public Update Page
                    </a>
                    @if($apkExternalUrl)
                        <p class="mt-3 text-xs text-slate-500">
                            This release is currently being served from the configured hosted build link.
                        </p>
                    @endif
                @else
                    <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                        No packaged Android APK has been uploaded to this HealthLink server yet. The page is ready, but the downloadable build still needs to be generated and published by the administrator.
                    </div>
                    <button type="button" disabled class="mt-5 inline-flex cursor-not-allowed items-center rounded-full bg-slate-200 px-5 py-2.5 text-sm font-medium text-slate-500">
                        Download Not Yet Available
                    </button>
                @endif
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Installation Notes</p>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                    <li>Use the same verified BHW email and password that you use on the web portal.</li>
                    <li>The very first login requires internet so the app can complete its initial barangay data sync.</li>
                    <li>After the first sync, the mobile app opens from local data even when the connection is weak or unavailable.</li>
                    <li>Always use the `Sync Now` action before logging out if you have pending field changes.</li>
                    <li>The release can be published either as a local server file or as a hosted APK link configured by the administrator.</li>
                </ul>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">What the app is for</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                The mobile app is designed for BHW field movement, not for the full administrative portal. It keeps the workflow light so household visits, resident checks, and draft capture are faster to perform on an Android phone.
            </p>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">What it can do</h3>
            <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
                <li>Search residents and households from the whole assigned barangay.</li>
                <li>Create and edit local field work only for the BHW&apos;s assigned purok.</li>
                <li>Log household visits with notes and photo capture.</li>
                <li>Queue records locally and sync them manually when internet is available.</li>
            </ul>
        </section>

        <section class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-slate-900">Why it stays simple</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600">
                This mobile companion intentionally avoids the heavy web modules. The goal is quick navigation, cleaner offline use, and safer sync behavior for frontline BHW work in the field.
            </p>
        </section>
    </div>
@endsection

<x-guest-layout
    page-title="HealthLink BHW App Update"
    heading="HealthLink BHW Android App"
    description="This public page is used by Barangay Health Workers to review the latest mobile release, update notes, and download instructions."
    eyebrow="BHW Mobile Release"
    hero-title="Offline-first field work for Tubigon BHW teams"
    hero-description="The HealthLink BHW mobile app supports offline resident lookup, assigned-purok field drafting, visit logging, and manual sync once the device is back online."
>
    <div class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon">Current Android Release</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ $release?->display_title ?? 'No Android release is available yet' }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        @if($releasePayload)
                            Version {{ $releasePayload['version_name'] }} · Code {{ number_format($releasePayload['version_code']) }} · {{ $releasePayload['update_mode_label'] }}
                        @else
                            The admin team has not published a downloadable BHW APK yet.
                        @endif
                    </p>
                </div>

                @if($releasePayload)
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $releasePayload['update_mode'] === 'required' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $releasePayload['update_mode_label'] }}
                    </span>
                @endif
            </div>

            @if($mobileSettings['maintenance_message'])
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
                    {{ $mobileSettings['maintenance_message'] }}
                </div>
            @endif

            @if($releasePayload)
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm leading-7 text-slate-700">
                    {!! $releasePayload['release_notes'] ? nl2br(e($releasePayload['release_notes'])) : 'No release notes were published for this version.' !!}
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    @if($downloadAvailable)
                        <a href="{{ route('mobile.bhw.download') }}" class="inline-flex items-center rounded-md bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Download Android APK
                        </a>
                    @endif
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Open HealthLink Web
                    </a>
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-dashed border-slate-300 px-5 py-4 text-sm text-slate-500">
                    Check back later or contact the admin team if you were asked to install a build but no public APK is showing yet.
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Download Instructions</h3>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-600">
                    <li>Tap the Android APK download button on this page.</li>
                    <li>Allow your phone to install apps from this browser if Android asks.</li>
                    <li>Open the downloaded APK and finish the installation.</li>
                    <li>Sign in again using your verified BHW email and password.</li>
                </ol>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Important Reminders</h3>
                <ul class="mt-4 space-y-2 text-sm leading-6 text-slate-600">
                    <li>The first login still needs internet for the initial barangay download.</li>
                    <li>Once the first sync finishes, the app can open from the local device data even while offline.</li>
                    <li>If an update becomes required while you are already in the field, you can still read your cached records offline and update later before syncing again.</li>
                </ul>
            </div>

            @if($releasePayload)
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Published</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        {{ $releasePayload['published_at_human'] ?? 'Not yet published' }}
                    </p>
                </div>
            @endif
        </section>
    </div>
</x-guest-layout>

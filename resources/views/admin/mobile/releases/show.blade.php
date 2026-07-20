@extends('layouts.admin')

@section('title', $release->display_title.' - HealthLink Admin')
@section('header', 'Mobile Release Details')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.mobile-releases.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Release Center
        </a>
        <a href="{{ route('admin.mobile-releases.edit', $release) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Edit
        </a>
        @if($canDownloadArtifact)
            <a href="{{ route('admin.mobile-releases.download', $release) }}" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                Download APK
            </a>
        @endif
        @if(!$isCurrentRelease)
            <form method="POST" action="{{ route('admin.mobile-releases.publish', $release) }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-md bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                    {{ $release->status === \App\Models\MobileAppRelease::STATUS_RETIRED ? 'Rollback to This Release' : 'Publish Release' }}
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon">{{ $release->status_label }}</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $release->display_title }}</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Version {{ $release->version_name }} · Code {{ number_format($release->version_code) }} · {{ $release->update_mode_label }}
                    </p>
                </div>

                @if($isCurrentRelease)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">
                        Live Download
                    </span>
                @endif
            </div>

            @if($release->release_notes)
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm leading-7 text-slate-700">
                    {!! nl2br(e($release->release_notes)) !!}
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-dashed border-slate-300 px-5 py-4 text-sm text-slate-500">
                    No release notes were saved for this version yet.
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div class="rounded-3xl bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-slate-900">Release Metadata</h3>
                <dl class="mt-4 space-y-3 text-sm text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt>Artifact source</dt>
                        <dd class="font-medium text-slate-900">{{ $release->artifact_source_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Published at</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $release->published_at?->format('F d, Y h:i A') ?? 'Not published yet' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Created by</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $release->createdBy?->name ?? 'System' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Published by</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $release->publishedBy?->name ?? 'Not published yet' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt>Public update page</dt>
                        <dd class="text-right">
                            <a href="{{ $publicUpdateUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-tubigon hover:text-tubigon-hover">
                                Open public page
                            </a>
                        </dd>
                    </div>
                </dl>
            </div>

            @if($release->rolledBackFrom)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                    <h3 class="text-lg font-semibold text-amber-900">Rollback Published</h3>
                    <p class="mt-2 text-sm leading-6 text-amber-800">
                        This release was published as a rollback from version {{ $release->rolledBackFrom->version_name }} (code {{ number_format($release->rolledBackFrom->version_code) }}).
                    </p>
                </div>
            @endif

            @if($release->artifact_source === \App\Models\MobileAppRelease::SOURCE_URL && $release->artifact_url)
                <div class="rounded-3xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Hosted Artifact</h3>
                    <a href="{{ $release->artifact_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 block break-all text-sm font-medium text-tubigon hover:text-tubigon-hover">
                        {{ $release->artifact_url }}
                    </a>
                </div>
            @endif
        </section>
    </div>
@endsection

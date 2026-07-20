@extends('layouts.admin')

@section('title', 'Create Mobile Release - HealthLink Admin')
@section('header', 'Create Mobile Release')

@section('actions')
    <a href="{{ route('admin.mobile-releases.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Back to Release Center
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
        <p class="text-sm leading-6 text-blue-900">
            Safer publishing flow: build the APK first through Expo/EAS, confirm it installs, then upload or link that finished build here. HealthLink will only switch the public download once you publish.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.mobile-releases.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @include('admin.mobile.releases._form', ['release' => $release, 'isEdit' => $isEdit])

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center rounded-md bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                Save Release
            </button>
            <a href="{{ route('admin.mobile-releases.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
@endsection

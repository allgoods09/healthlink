@extends('layouts.admin')

@section('title', 'Edit Mobile Release - HealthLink Admin')
@section('header', 'Edit Mobile Release')

@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.mobile-releases.show', $release) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            View Release
        </a>
        <a href="{{ route('admin.mobile-releases.index') }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Release Center
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
        <p class="text-sm leading-6 text-amber-900">
            Editing a published release does not force BHW devices to update by itself. The public download changes only when this release remains published or when another release is promoted live.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.mobile-releases.update', $release) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        @include('admin.mobile.releases._form', ['release' => $release, 'isEdit' => $isEdit])

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center rounded-md bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                Save Changes
            </button>
            <a href="{{ route('admin.mobile-releases.show', $release) }}" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>
    </form>
@endsection

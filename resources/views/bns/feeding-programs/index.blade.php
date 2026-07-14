@extends('layouts.portal')

@section('title', 'BNS Feeding Programs - HealthLink')
@section('header', 'Feeding Programs')
@section('subheader', 'Supplementary feeding batches with enrolled children, attendance, and weekly weight progress for nutrition follow-up.')

@section('actions')
    <a href="{{ route('bns.feeding-programs.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        Create Feeding Program
    </a>
@endsection

@section('content')
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm text-slate-500">Active Programs</p>
            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($activeProgramCount) }}</p>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm md:col-span-2">
            <p class="text-sm text-slate-500">Enrollment Scope</p>
            <p class="mt-2 text-sm leading-6 text-slate-700">
                BNS may manually enroll any verified child aged 0 to 71 months, even outside the TCL, while the latest watchlist remains the primary recommendation pool for follow-up feeding support.
            </p>
        </div>
    </div>

    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bns.feeding-programs.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="search" class="block text-sm font-medium text-slate-700">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Program name or description" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                </div>
                <div>
                    <label for="program_status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="program_status" id="program_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        @foreach($programStatuses as $status => $label)
                            <option value="{{ $status }}" @selected(request('program_status') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Apply Filters
                    </button>
                    <a href="{{ route('bns.feeding-programs.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Program</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Rosters</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($feedingPrograms as $feedingProgram)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $feedingProgram->name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $feedingProgram->campaignPeriod?->name ?? 'No linked campaign period' }}</p>
                                @if($feedingProgram->description)
                                    <p class="mt-2 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($feedingProgram->description, 110) }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                {{ $feedingProgram->starts_on?->format('M d, Y') ?? 'No start date' }}
                                <span class="text-slate-400">to</span>
                                {{ $feedingProgram->ends_on?->format('M d, Y') ?? 'Open-ended' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <p>{{ number_format($feedingProgram->active_enrollments_count) }} active child(ren)</p>
                                <p class="mt-1">{{ number_format($feedingProgram->enrollments_count) }} total enrollment(s)</p>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $feedingProgram->program_status === \App\Models\FeedingProgram::STATUS_ACTIVE ? 'bg-emerald-100 text-emerald-800' : ($feedingProgram->program_status === \App\Models\FeedingProgram::STATUS_COMPLETED ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $feedingProgram->program_status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('bns.feeding-programs.show', $feedingProgram) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                                    <a href="{{ route('bns.feeding-programs.edit', $feedingProgram) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No feeding programs match the current filters yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-6 py-4">
            {{ $feedingPrograms->links() }}
        </div>
    </div>
@endsection

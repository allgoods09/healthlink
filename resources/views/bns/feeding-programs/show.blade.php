@extends('layouts.portal')

@section('title', 'Feeding Program Detail - HealthLink')
@section('header', 'Feeding Program Detail')
@section('subheader', 'Manage enrolled children, attendance, and weekly weight progress inside one feeding cycle.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('bns.feeding-programs.edit', $feedingProgram) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Edit Program
        </a>
        <a href="{{ route('bns.feeding-programs.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Programs
        </a>
    </div>
@endsection

@section('content')
    @php
        $eligibleChildSearchOptions = $eligibleChildren->map(fn ($child) => [
            'value' => $child->id,
            'label' => $child->formal_name,
            'description' => $child->household?->purok?->display_name ?? 'Unknown purok',
            'search' => collect([
                $child->formal_name,
                $child->official_resident_code,
                $child->household?->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="space-y-6">
            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $feedingProgram->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $feedingProgram->campaignPeriod?->name ?? 'No linked campaign period' }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $feedingProgram->program_status === \App\Models\FeedingProgram::STATUS_ACTIVE ? 'bg-emerald-100 text-emerald-800' : ($feedingProgram->program_status === \App\Models\FeedingProgram::STATUS_COMPLETED ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800') }}">
                            {{ $feedingProgram->program_status_label }}
                        </span>
                    </div>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Active Children</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($feedingProgram->active_enrollments_count) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Total Enrollments</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($feedingProgram->enrollments_count) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm text-slate-500">Schedule</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">
                            {{ $feedingProgram->starts_on?->format('M d, Y') ?? 'No start date' }}
                            <span class="text-slate-400">to</span>
                            {{ $feedingProgram->ends_on?->format('M d, Y') ?? 'Open-ended' }}
                        </p>
                    </div>
                </div>
                @if($feedingProgram->description)
                    <div class="border-t border-slate-200 px-6 py-5 text-sm leading-6 text-slate-700">
                        {{ $feedingProgram->description }}
                    </div>
                @endif
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Enrolled Children</h3>
                    <p class="mt-1 text-sm text-slate-500">Pick a child from the roster to log attendance or weekly weight progress.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Child</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Baseline</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Logs</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($enrollments as $enrollment)
                                <tr class="{{ $selectedEnrollment && $selectedEnrollment->id === $enrollment->id ? 'bg-blue-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-semibold text-slate-900">{{ $enrollment->resident?->formal_name ?? 'Unknown resident' }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $enrollment->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <p>{{ $enrollment->baseline_weight_kg ? $enrollment->baseline_weight_kg.' kg' : 'No baseline weight' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ $enrollment->baseline_nutritional_status ?: 'No baseline status noted' }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <p>{{ number_format($enrollment->attendances_count) }} attendance log(s)</p>
                                        <p class="mt-1">{{ number_format($enrollment->progress_logs_count) }} progress log(s)</p>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $enrollment->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $enrollment->enrollment_status_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <a href="{{ route('bns.feeding-programs.show', ['feedingProgram' => $feedingProgram, 'enrollment' => $enrollment->id]) }}" class="text-tubigon hover:text-tubigon-hover">Select</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                        No child has been enrolled in this feeding program yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($watchlistSuggestions->isNotEmpty())
                <div class="rounded-[28px] border border-amber-200 bg-amber-50 shadow-sm">
                    <div class="border-b border-amber-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-amber-900">Suggested TCL Enrollments</h3>
                        <p class="mt-1 text-sm text-amber-800">Latest target-client cases not yet inside this feeding batch.</p>
                    </div>
                    <div class="divide-y divide-amber-200">
                        @foreach($watchlistSuggestions as $suggestion)
                            <div class="px-6 py-4">
                                <p class="text-sm font-semibold text-amber-900">{{ $suggestion->resident?->formal_name ?? 'Unknown resident' }}</p>
                                <p class="mt-1 text-sm text-amber-800">
                                    {{ $suggestion->resident?->household?->purok?->display_name ?? 'Unknown purok' }}
                                    · {{ implode(', ', $suggestion->target_client_reasons) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Add Enrollment</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('bns.feeding-programs.enrollments.store', $feedingProgram) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="resident_id" class="block text-sm font-medium text-slate-700">Verified Child</label>
                            <x-searchable-record-select
                                name="resident_id"
                                id="resident_id"
                                :options="$eligibleChildSearchOptions"
                                :selected="old('resident_id')"
                                placeholder="Search child name"
                                empty-message="No eligible child matches your search."
                                required
                            />
                        </div>
                        <div>
                            <label for="enrolled_on" class="block text-sm font-medium text-slate-700">Enrolled On</label>
                            <input type="date" name="enrolled_on" id="enrolled_on" value="{{ old('enrolled_on', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="baseline_weight_kg" class="block text-sm font-medium text-slate-700">Baseline Weight</label>
                                <input type="number" step="0.01" min="0.5" max="60" name="baseline_weight_kg" id="baseline_weight_kg" value="{{ old('baseline_weight_kg') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label for="baseline_nutritional_status" class="block text-sm font-medium text-slate-700">Baseline Status</label>
                                <input type="text" name="baseline_nutritional_status" id="baseline_nutritional_status" value="{{ old('baseline_nutritional_status') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                        </div>
                        <div>
                            <label for="completion_notes" class="block text-sm font-medium text-slate-700">Enrollment Notes</label>
                            <textarea name="completion_notes" id="completion_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('completion_notes') }}</textarea>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                            Enroll Child
                        </button>
                    </form>
                </div>
            </section>

            @if($selectedEnrollment)
                <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Selected Enrollment</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $selectedEnrollment->resident?->formal_name ?? 'Unknown resident' }}</p>
                    </div>
                    <div class="space-y-6 p-6">
                        <form method="POST" action="{{ route('bns.feeding-programs.enrollments.update', [$feedingProgram, $selectedEnrollment]) }}" class="space-y-4">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label for="is_active" class="block text-sm font-medium text-slate-700">Enrollment Status</label>
                                <select name="is_active" id="is_active" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                    <option value="1" @selected(old('is_active', $selectedEnrollment->is_active ? '1' : '0') === '1')>Active</option>
                                    <option value="0" @selected(old('is_active', $selectedEnrollment->is_active ? '1' : '0') === '0')>Completed</option>
                                </select>
                            </div>
                            <div>
                                <label for="completion_notes_status" class="block text-sm font-medium text-slate-700">Completion Notes</label>
                                <textarea name="completion_notes" id="completion_notes_status" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('completion_notes', $selectedEnrollment->completion_notes) }}</textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                                Save Enrollment Status
                            </button>
                        </form>

                        <form method="POST" action="{{ route('bns.feeding-programs.attendances.store', [$feedingProgram, $selectedEnrollment]) }}" class="space-y-4 border-t border-slate-200 pt-6">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="attendance_date" class="block text-sm font-medium text-slate-700">Attendance Date</label>
                                    <input type="date" name="attendance_date" id="attendance_date" value="{{ old('attendance_date', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                </div>
                                <div>
                                    <label for="attendance_status" class="block text-sm font-medium text-slate-700">Attendance Status</label>
                                    <select name="attendance_status" id="attendance_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                        @foreach(\App\Models\FeedingProgramAttendance::STATUSES as $status => $label)
                                            <option value="{{ $status }}" @selected(old('attendance_status', \App\Models\FeedingProgramAttendance::STATUS_PRESENT) === $status)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="attendance_notes" class="block text-sm font-medium text-slate-700">Attendance Notes</label>
                                <textarea name="notes" id="attendance_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                                Add Attendance
                            </button>
                        </form>

                        <form method="POST" action="{{ route('bns.feeding-programs.progress.store', [$feedingProgram, $selectedEnrollment]) }}" class="space-y-4 border-t border-slate-200 pt-6">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="logged_on" class="block text-sm font-medium text-slate-700">Progress Date</label>
                                    <input type="date" name="logged_on" id="logged_on" value="{{ old('logged_on', now()->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                </div>
                                <div>
                                    <label for="week_number" class="block text-sm font-medium text-slate-700">Week Number</label>
                                    <input type="number" name="week_number" id="week_number" min="1" max="104" value="{{ old('week_number') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                                </div>
                            </div>
                            <div>
                                <label for="progress_weight_kg" class="block text-sm font-medium text-slate-700">Current Weight</label>
                                <input type="number" step="0.01" min="0.5" max="60" name="weight_kg" id="progress_weight_kg" value="{{ old('weight_kg') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label for="progress_remarks" class="block text-sm font-medium text-slate-700">Progress Notes</label>
                                <textarea name="remarks" id="progress_remarks" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('remarks') }}</textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
                                Add Weekly Progress
                            </button>
                        </form>
                    </div>
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Recent Attendance & Progress</h3>
                    </div>
                    <div class="grid divide-y divide-slate-200 md:grid-cols-2 md:divide-x md:divide-y-0">
                        <div class="p-6">
                            <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Attendance</h4>
                            <div class="mt-4 space-y-3">
                                @forelse($selectedEnrollment->attendances as $attendance)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <p class="text-sm font-semibold text-slate-900">{{ $attendance->attendance_date?->format('M d, Y') }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $attendance->attendance_status_label }}</p>
                                        @if($attendance->notes)
                                            <p class="mt-2 text-sm text-slate-500">{{ $attendance->notes }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No attendance entries yet.</p>
                                @endforelse
                            </div>
                        </div>
                        <div class="p-6">
                            <h4 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Weekly Progress</h4>
                            <div class="mt-4 space-y-3">
                                @forelse($selectedEnrollment->progressLogs as $progressLog)
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <p class="text-sm font-semibold text-slate-900">{{ $progressLog->logged_on?->format('M d, Y') }}</p>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ $progressLog->weight_kg ? $progressLog->weight_kg.' kg' : 'No weight recorded' }}
                                            @if($progressLog->week_number)
                                                · Week {{ $progressLog->week_number }}
                                            @endif
                                        </p>
                                        @if($progressLog->remarks)
                                            <p class="mt-2 text-sm text-slate-500">{{ $progressLog->remarks }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No weekly progress entries yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>
@endsection

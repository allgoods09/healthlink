@extends('layouts.portal')

@section('title', 'BHW Campaign Tasks - HealthLink')
@section('header', 'Campaign Tasks')
@section('subheader', 'Assigned rosters for immunization, deworming, maintenance distribution, and other community health drives.')

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="p-5">
            <form method="GET" action="{{ route('bhw.campaigns.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">All statuses</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        <option value="skipped" @selected(request('status') === 'skipped')>Skipped</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="checkbox" name="due_today" value="1" @checked(request()->boolean('due_today')) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                        <span class="text-sm text-slate-700">Due today only</span>
                    </label>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-xl bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">Apply Filters</button>
                    <a href="{{ route('bhw.campaigns.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-500">Tasks Due Today</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($dueTodayCount) }}</p>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Campaign</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Target</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($assignments as $assignment)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $assignment->campaign?->title ?? 'Untitled campaign' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $assignment->campaign?->campaign_type_label ?? 'Campaign type not set' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $assignment->target_label }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $assignment->assignment_status_label }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <a href="{{ route('bhw.campaigns.show', $assignment) }}" class="text-tubigon hover:text-tubigon-hover">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">No campaign tasks are assigned to you yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $assignments->links() }}
        </div>
    </div>
@endsection

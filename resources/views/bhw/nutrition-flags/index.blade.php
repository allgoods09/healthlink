@extends('layouts.portal')

@section('title', 'BHW Nutrition Flags - HealthLink')
@section('header', 'Nutrition Flags')
@section('subheader', 'Track child referrals you sent to the BNS for official nutrition assessment.')

@section('actions')
    <a href="{{ route('bhw.nutrition-flags.create') }}" class="inline-flex items-center rounded-full bg-tubigon px-4 py-2 text-sm font-medium text-white hover:bg-tubigon-hover">
        New Nutrition Flag
    </a>
@endsection

@section('content')
    <div class="mb-6 rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-500">Open Flags</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($openCount) }}</p>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Child</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reason</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse($flags as $flag)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="text-sm font-semibold text-slate-900">{{ $flag->resident?->formal_name ?? 'Unknown child' }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $flag->resident?->household?->purok?->display_name ?? 'Unknown purok' }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $flag->flag_reason }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ ucfirst($flag->flag_status) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-500">No nutrition flags have been submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-6 py-4">
            {{ $flags->links() }}
        </div>
    </div>
@endsection

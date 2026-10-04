@php
    $historicalMembers = $household->residents->reject->isCurrentPopulation();
@endphp
@if($historicalMembers->isNotEmpty())
    <details class="mt-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Historical Attached Residents ({{ $historicalMembers->count() }})</summary>
        <p class="mt-2 text-sm text-slate-500">These records remain attached for history and are not included in current membership.</p>
        <div class="mt-4 divide-y divide-slate-200">
            @foreach(\App\Support\HouseholdMemberOrdering::ordered($household, $historicalMembers) as $member)
                <div class="py-3 text-sm">
                    <p class="font-medium text-slate-900">{{ $member->formal_name }}</p>
                    <p class="text-slate-500">{{ $member->relationship_to_head }} &middot; {{ $member->resident_status_label }}</p>
                </div>
            @endforeach
        </div>
    </details>
@endif

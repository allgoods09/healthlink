@extends($layout)
@section('title', 'Household Relationship Review - HealthLink')
@section('header', 'Change Household Head')
@section('content')
<form method="POST" action="{{ $action }}" class="mx-auto max-w-4xl space-y-6">
    @csrf
    @method($method)
    <x-forward-form-inputs :values="$payload" />
    <input type="hidden" name="head_review_token" value="{{ $token }}">
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Household Relationship Review</h2>
        <p class="mt-2 text-sm text-slate-600">Because household relationships are recorded relative to the household head, review the relationships of all other household members before confirming. Relationships will not be automatically recalculated.</p>
        @foreach($errors->all() as $error)<p class="mt-2 text-sm text-red-600">{{ $error }}</p>@endforeach
    </div>
    @foreach($plans as $id => $plan)
        @php
            $household = $households[$id];
            $members = $household->residents->where('id', '!=', $plan['exclude_id'] ?? 0);
            $currentHead = $household->residents->firstWhere('id', $household->head_resident_id);
        @endphp
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm" x-data="{ replacement: '{{ old('head_reviews.'.$id.'.candidate_id', $plan['candidate_id'] ?? '') }}' }">
            <h3 class="font-semibold text-slate-900">Household #{{ $household->household_no }}</h3>
            <p class="mt-2 text-sm text-slate-600">Current Household Head: <strong>{{ $currentHead?->full_name ?: 'No designated head' }}</strong></p>
            @if($plan['choose_candidate'] ?? false)
                <label class="mt-4 block text-sm font-medium text-slate-700" for="replacement-{{ $id }}">Replacement Household Head</label>
                <select id="replacement-{{ $id }}" name="head_reviews[{{ $id }}][candidate_id]" x-model="replacement" class="mt-1 w-full rounded-md border-slate-300" required>
                    <option value="">Choose a remaining household member</option>
                    @foreach($members as $member)<option value="{{ $member->id }}">{{ $member->full_name }}</option>@endforeach
                </select>
                <p class="mt-2 text-sm text-slate-600">The departing head's remaining household needs an explicit replacement. Review each member's relationship to the person selected above.</p>
            @else
                <p class="mt-2 text-sm text-slate-600">New Household Head: <strong>{{ $plan['candidate_name'] }}</strong></p>
                <p class="mt-2 text-sm text-slate-600">Review each member's relationship to {{ $plan['candidate_name'] }}.</p>
            @endif
            <div class="mt-4 space-y-4">
                @foreach($members as $member)
                    @continue($member->id === ($plan['candidate_id'] ?? null))
                    <div>
                        <label for="member-{{ $id }}-{{ $member->id }}" class="block text-sm font-medium text-slate-700">{{ $member->full_name }}</label>
                        <p class="mt-1 text-sm text-slate-500">{{ $member->sex ?: 'Sex not recorded' }} &middot; {{ \App\Support\HouseholdMemberOrdering::birthDate($member) ? $member->age.' years old' : 'Age not recorded' }}</p>
                        <x-household-relationship-select id="member-{{ $id }}-{{ $member->id }}" name="head_reviews[{{ $id }}][relationships][{{ $member->id }}]" :current="$member->relationship_to_head" :selected="old('head_reviews.'.$id.'.relationships.'.$member->id)" :review="true" x-bind:disabled="replacement === '{{ $member->id }}'" />
                        <p x-cloak x-show="replacement === '{{ $member->id }}'" class="mt-1 text-sm text-slate-500">Household Head</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    <div class="flex justify-end gap-2">
        <x-record-action :href="$cancelUrl" variant="back">Cancel</x-record-action>
        <x-record-action type="submit" variant="edit">Confirm Change</x-record-action>
    </div>
</form>
@endsection

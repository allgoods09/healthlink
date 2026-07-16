@extends('layouts.portal')

@php
    $routePrefix = $routePrefix ?? 'bhw';
@endphp

@section('title', $pageTitle ?? 'Resident Correction Request - HealthLink')
@section('header', $pageHeader ?? 'Resident Correction Request')
@section('subheader', $pageSubheader ?? 'Submit civil profile corrections for Secretary review. Verified records remain read-only to BHW users.')

@section('actions')
    <a href="{{ route($routePrefix.'.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Tracking
    </a>
@endsection

@section('content')
    @php
        $residentSearchOptions = $residentOptions->map(fn ($residentOption) => [
            'value' => $residentOption->id,
            'label' => $residentOption->formal_name,
            'description' => $residentOption->household?->purok?->display_name ?? 'Unknown purok',
            'search' => collect([
                $residentOption->formal_name,
                $residentOption->official_resident_code,
                $residentOption->household?->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
        $householdSearchOptions = $householdOptions->map(fn ($householdOption) => [
            'value' => $householdOption->id,
            'label' => $householdOption->full_identifier,
            'description' => $householdOption->purok?->display_name ?? 'Unknown purok',
            'search' => collect([
                $householdOption->full_identifier,
                $householdOption->household_address,
                $householdOption->purok?->display_name,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">Please review the resident correction details.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($routePrefix.'.update-requests.store-resident') }}" class="space-y-6">
        @csrf
        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Resident Selection</h2>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div>
                    <label for="subject_id" class="block text-sm font-medium text-slate-700">Verified Resident</label>
                    <x-searchable-record-select
                        name="subject_id"
                        id="subject_id"
                        :options="$residentSearchOptions"
                        :selected="old('subject_id', $selectedResident?->id)"
                        placeholder="Search resident name"
                        empty-message="No resident matches your search."
                        required
                    />
                </div>
                <div>
                    <label for="household_id" class="block text-sm font-medium text-slate-700">Proposed Household</label>
                    <x-searchable-record-select
                        name="household_id"
                        id="household_id"
                        :options="$householdSearchOptions"
                        :selected="old('household_id', $selectedResident?->household_id)"
                        placeholder="Search household number or address"
                        empty-message="No household matches your search."
                    />
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Proposed Resident Details</h2>
            </div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <div><label class="block text-sm font-medium text-slate-700">PhilSys Card No.</label><input type="text" name="philsys_card_no" value="{{ old('philsys_card_no', $selectedResident?->philsys_card_no) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Relationship to Head</label><input type="text" name="relationship_to_head" value="{{ old('relationship_to_head', $selectedResident?->relationship_to_head) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Last Name</label><input type="text" name="last_name" value="{{ old('last_name', $selectedResident?->last_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">First Name</label><input type="text" name="first_name" value="{{ old('first_name', $selectedResident?->first_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Middle Name</label><input type="text" name="middle_name" value="{{ old('middle_name', $selectedResident?->middle_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Suffix</label><input type="text" name="suffix" value="{{ old('suffix', $selectedResident?->suffix) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Birth Date</label><input type="date" name="birth_date" value="{{ old('birth_date', optional($selectedResident?->birth_date)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Birth Place</label><input type="text" name="birth_place" value="{{ old('birth_place', $selectedResident?->birth_place) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Sex</label><select name="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"><option value="Female" @selected(old('sex', $selectedResident?->sex) === 'Female')>Female</option><option value="Male" @selected(old('sex', $selectedResident?->sex) === 'Male')>Male</option></select></div>
                <div><label class="block text-sm font-medium text-slate-700">Civil Status</label><input type="text" name="civil_status" value="{{ old('civil_status', $selectedResident?->civil_status) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Citizenship</label><input type="text" name="citizenship" value="{{ old('citizenship', $selectedResident?->citizenship) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Religion</label><input type="text" name="religion" value="{{ old('religion', $selectedResident?->religion) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Contact Number</label><input type="text" name="contact_number" value="{{ old('contact_number', $selectedResident?->contact_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Email Address</label><input type="email" name="email_address" value="{{ old('email_address', $selectedResident?->email_address) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Resident Status</label><select name="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"><option value="active" @selected(old('resident_status', $selectedResident?->resident_status) === 'active')>Active</option><option value="deceased" @selected(old('resident_status', $selectedResident?->resident_status) === 'deceased')>Deceased</option><option value="relocated" @selected(old('resident_status', $selectedResident?->resident_status) === 'relocated')>Relocated</option></select></div>
                <div><label class="block text-sm font-medium text-slate-700">Moved In</label><input type="date" name="moved_in_at" value="{{ old('moved_in_at', optional($selectedResident?->moved_in_at)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Moved Out</label><input type="date" name="moved_out_at" value="{{ old('moved_out_at', optional($selectedResident?->moved_out_at)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div><label class="block text-sm font-medium text-slate-700">Date of Death</label><input type="date" name="date_of_death" value="{{ old('date_of_death', optional($selectedResident?->date_of_death)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700">Status Notes</label><textarea name="status_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('status_notes', $selectedResident?->status_notes) }}</textarea></div>
                <div class="md:col-span-2"><label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $selectedResident?->is_active ?? true)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon"><span class="text-sm text-slate-700">Keep resident active in the verified registry</span></label></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-slate-700">Reason for Request</label><textarea name="request_reason" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('request_reason') }}</textarea></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">Submit Resident Correction</button>
            <a href="{{ route($routePrefix.'.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">Cancel</a>
        </div>
    </form>
@endsection

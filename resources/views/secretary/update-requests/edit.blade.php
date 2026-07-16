@extends('layouts.portal')

@section('title', 'Review Correction Request - HealthLink')
@section('header', 'Review Correction Request')
@section('subheader', 'Confirm or adjust the proposed values, then apply the final approved version back into the verified registry.')

@section('actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('secretary.update-requests.show', $profileUpdateRequest) }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            View Request
        </a>
        <a href="{{ route('secretary.update-requests.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
            Back to Queue
        </a>
    </div>
@endsection

@section('content')
    @php
        $proposed = $profileUpdateRequest->proposed_changes ?? [];
        $subject = $profileUpdateRequest->subject_type === \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT
            ? $profileUpdateRequest->resident
            : $profileUpdateRequest->household;
        $householdSearchOptions = $households->map(fn ($household) => [
            'value' => $household->id,
            'label' => $household->purok?->display_name.' · Household #'.$household->household_no,
            'description' => $household->household_address ?: 'No household address',
            'search' => collect([
                $household->purok?->display_name,
                $household->household_no ? 'household '.$household->household_no : null,
                $household->household_address,
                $household->headResident?->formal_name,
            ])->filter()->implode(' '),
        ])->values()->all();
        $headResidentSearchOptions = collect($subject?->residents ?? [])->map(fn ($resident) => [
            'value' => $resident->id,
            'label' => $resident->formal_name,
            'search' => collect([
                $resident->formal_name,
                $resident->official_resident_code,
            ])->filter()->implode(' '),
        ])->values()->all();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="rounded-[24px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Apply Final Changes</h3>
            </div>
            <div class="p-6">
                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="font-semibold">Please review the correction approval form.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('secretary.update-requests.approve', $profileUpdateRequest) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    @if($profileUpdateRequest->subject_type === \App\Models\ProfileUpdateRequest::SUBJECT_RESIDENT)
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Household</label>
                                <x-searchable-record-select
                                    name="household_id"
                                    :options="$householdSearchOptions"
                                    :selected="old('household_id', data_get($proposed, 'household_id', $subject?->household_id))"
                                    placeholder="Search household number or address"
                                    empty-message="No household matches your search."
                                    required
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">PhilSys ID</label>
                                <input type="text" name="philsys_card_no" value="{{ old('philsys_card_no', data_get($proposed, 'philsys_card_no', $subject?->philsys_card_no)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Last Name</label>
                                <input type="text" name="last_name" value="{{ old('last_name', data_get($proposed, 'last_name', $subject?->last_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">First Name</label>
                                <input type="text" name="first_name" value="{{ old('first_name', data_get($proposed, 'first_name', $subject?->first_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Middle Name</label>
                                <input type="text" name="middle_name" value="{{ old('middle_name', data_get($proposed, 'middle_name', $subject?->middle_name)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Suffix</label>
                                <input type="text" name="suffix" value="{{ old('suffix', data_get($proposed, 'suffix', $subject?->suffix)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Birth Date</label>
                                <input type="date" name="birth_date" value="{{ old('birth_date', data_get($proposed, 'birth_date', optional($subject?->birth_date)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Birth Place</label>
                                <input type="text" name="birth_place" value="{{ old('birth_place', data_get($proposed, 'birth_place', $subject?->birth_place)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Sex</label>
                                <select name="sex" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    <option value="Male" {{ old('sex', data_get($proposed, 'sex', $subject?->sex)) === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ old('sex', data_get($proposed, 'sex', $subject?->sex)) === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Civil Status</label>
                                <input type="text" name="civil_status" value="{{ old('civil_status', data_get($proposed, 'civil_status', $subject?->civil_status)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Citizenship</label>
                                <input type="text" name="citizenship" value="{{ old('citizenship', data_get($proposed, 'citizenship', $subject?->citizenship)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Religion</label>
                                <input type="text" name="religion" value="{{ old('religion', data_get($proposed, 'religion', $subject?->religion)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Contact Number</label>
                                <input type="text" name="contact_number" value="{{ old('contact_number', data_get($proposed, 'contact_number', $subject?->contact_number)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Email Address</label>
                                <input type="email" name="email_address" value="{{ old('email_address', data_get($proposed, 'email_address', $subject?->email_address)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Relationship to Head</label>
                                <input type="text" name="relationship_to_head" value="{{ old('relationship_to_head', data_get($proposed, 'relationship_to_head', $subject?->relationship_to_head)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Resident Status</label>
                                <select name="resident_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    <option value="active" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="deceased" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'deceased' ? 'selected' : '' }}>Deceased</option>
                                    <option value="relocated" {{ old('resident_status', data_get($proposed, 'resident_status', $subject?->resident_status)) === 'relocated' ? 'selected' : '' }}>Relocated</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Moved In At</label>
                                <input type="date" name="moved_in_at" value="{{ old('moved_in_at', data_get($proposed, 'moved_in_at', optional($subject?->moved_in_at)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Moved Out At</label>
                                <input type="date" name="moved_out_at" value="{{ old('moved_out_at', data_get($proposed, 'moved_out_at', optional($subject?->moved_out_at)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Date of Death</label>
                                <input type="date" name="date_of_death" value="{{ old('date_of_death', data_get($proposed, 'date_of_death', optional($subject?->date_of_death)->format('Y-m-d'))) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div class="md:col-span-2">
                                <input type="hidden" name="is_active" value="0">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', data_get($proposed, 'is_active', $subject?->is_active)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Keep resident record active</span>
                                </label>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700">Status Notes</label>
                                <textarea name="status_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('status_notes', data_get($proposed, 'status_notes', $subject?->status_notes)) }}</textarea>
                            </div>
                        </div>
                    @else
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Purok</label>
                                <select name="purok_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                                    @foreach($puroks as $purok)
                                        <option value="{{ $purok->id }}" {{ (string) old('purok_id', data_get($proposed, 'purok_id', $subject?->purok_id)) === (string) $purok->id ? 'selected' : '' }}>
                                            {{ $purok->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Household No.</label>
                                <input type="text" name="household_no" value="{{ old('household_no', data_get($proposed, 'household_no', $subject?->household_no)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700">Household Address</label>
                                <textarea name="household_address" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" required>{{ old('household_address', data_get($proposed, 'household_address', $subject?->household_address)) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Water Source</label>
                                <input type="text" name="drinking_water_source" value="{{ old('drinking_water_source', data_get($proposed, 'drinking_water_source', $subject?->drinking_water_source)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Toilet Type</label>
                                <input type="text" name="sanitary_toilet_type" value="{{ old('sanitary_toilet_type', data_get($proposed, 'sanitary_toilet_type', $subject?->sanitary_toilet_type)) }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Head of Household</label>
                                <x-searchable-record-select
                                    name="head_resident_id"
                                    :options="$headResidentSearchOptions"
                                    :selected="old('head_resident_id', data_get($proposed, 'head_resident_id', $subject?->head_resident_id))"
                                    placeholder="Search resident name"
                                    empty-message="No resident matches your search."
                                />
                            </div>
                            <div>
                                <input type="hidden" name="has_sanitary_toilet" value="0">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="has_sanitary_toilet" value="1" {{ old('has_sanitary_toilet', data_get($proposed, 'has_sanitary_toilet', $subject?->has_sanitary_toilet)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Has sanitary toilet</span>
                                </label>
                            </div>
                            <div>
                                <input type="hidden" name="is_social_aid_beneficiary" value="0">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_social_aid_beneficiary" value="1" {{ old('is_social_aid_beneficiary', data_get($proposed, 'is_social_aid_beneficiary', $subject?->is_social_aid_beneficiary)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Social aid beneficiary</span>
                                </label>
                            </div>
                            <div>
                                <input type="hidden" name="is_active" value="0">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', data_get($proposed, 'is_active', $subject?->is_active)) ? 'checked' : '' }} class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                                    <span class="ml-2 text-sm text-slate-700">Keep household active</span>
                                </label>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Secretary Review Notes</label>
                        <textarea name="review_notes" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('review_notes', $profileUpdateRequest->review_notes) }}</textarea>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                            Apply Approved Changes
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Request Reason</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">{{ $profileUpdateRequest->request_reason ?: 'No reason provided.' }}</p>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-tubigon/70">Reject Instead</p>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Reject only if the requested correction is unsupported or inaccurate. If the field report is directionally correct, adjust the values in the approval form instead and apply the cleaned version.
                </p>

                <form action="{{ route('secretary.update-requests.reject', $profileUpdateRequest) }}" method="POST" class="mt-5" onsubmit="return captureRejectionReason(this, '{{ addslashes($profileUpdateRequest->subject_name) }}')">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="review_notes" value="">
                    <button type="submit" class="inline-flex items-center rounded-full bg-rose-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-rose-700">
                        Reject Correction Request
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function captureRejectionReason(form, subjectName) {
            const reason = window.prompt(`Enter a rejection note for ${subjectName}:`);

            if (!reason) {
                return false;
            }

            form.querySelector('input[name="review_notes"]').value = reason;

            return true;
        }
    </script>
@endpush

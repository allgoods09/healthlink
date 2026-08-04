@php
    $containerClass = $containerClass ?? 'rounded-[28px] border border-slate-200 bg-white shadow-sm';
    $household = $resident->household;
    $purok = $household?->purok;
    $barangay = $purok?->barangay;
    $notRecorded = 'Not recorded';
@endphp

<section class="{{ $containerClass }} overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon/75">Resident Profile</p>
                <h2 class="mt-2 break-words text-xl font-semibold text-slate-900">{{ $resident->formal_name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $resident->official_resident_code ?: 'No resident code assigned' }}</p>
            </div>
            <div class="flex flex-wrap gap-2 sm:justify-end">
                @if($resident->is_household_head)
                    <span class="inline-flex rounded-md border border-tubigon/20 bg-tubigon/10 px-2.5 py-1 text-xs font-semibold text-tubigon">Household Head</span>
                @endif
                <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $resident->resident_status_label }}</span>
            </div>
        </div>
    </div>

    <div class="space-y-6 p-6">
        <section>
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-900">Personal Details</h3>
                <span class="text-xs font-medium text-slate-400">Identity and civil information</span>
            </div>
            <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">PhilSys Card No.</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->philsys_card_no ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Sex</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->sex ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Age</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->birth_date ? $resident->age.' years old' : $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Birth Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->birth_date?->format('F j, Y') ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Birth Place</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->birth_place ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Civil Status</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->civil_status ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Citizenship</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->citizenship ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Religion</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->religion ?: $notRecorded }}</dd>
                </div>
            </dl>
        </section>

        <section>
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-900">Contact and Household</h3>
                <span class="text-xs font-medium text-slate-400">Location and household assignment</span>
            </div>
            <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-3">
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Contact Number</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->contact_number ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Email Address</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->email_address ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Barangay</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $barangay?->name ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Purok</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $purok?->display_name ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Household</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household?->full_identifier ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Head of Household</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household?->headResident?->formal_name ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3 xl:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Relationship to Head</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $resident->relationship_to_head ?: $notRecorded }}</dd>
                </div>
            </dl>
        </section>

        <section>
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-900">Registry Tracking</h3>
                <span class="text-xs font-medium text-slate-400">Civil record lifecycle</span>
            </div>
            <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Record Status</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->resident_status_label }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Move-In Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->moved_in_at?->format('F j, Y') ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Move-Out Date</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->moved_out_at?->format('F j, Y') ?: $notRecorded }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Date of Death</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $resident->date_of_death?->format('F j, Y') ?: $notRecorded }}</dd>
                </div>
            </dl>

            @if($resident->status_notes)
                <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Status Notes</p>
                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $resident->status_notes }}</p>
                </div>
            @endif
        </section>
    </div>
</section>

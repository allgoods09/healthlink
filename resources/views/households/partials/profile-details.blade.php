@php
    $containerClass = $containerClass ?? 'rounded-[28px] border border-slate-200 bg-white shadow-sm';
    $purok = $household->purok;
    $barangay = $purok?->barangay;
    $toiletStatus = is_null($household->has_sanitary_toilet)
        ? 'Not recorded'
        : ($household->has_sanitary_toilet ? 'Has sanitary toilet' : 'No sanitary toilet');

    if ($household->has_sanitary_toilet && $household->sanitary_toilet_type) {
        $toiletStatus .= ' · '.$household->sanitary_toilet_type;
    }
@endphp

<section class="{{ $containerClass }} overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon/75">Household Profile</p>
                <h2 class="mt-2 break-words text-xl font-semibold text-slate-900">{{ $household->full_identifier }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $household->official_household_code ?: 'No household code assigned' }}</p>
            </div>
            <div class="flex flex-wrap gap-2 sm:justify-end">
                <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $household->is_active ? 'Active household' : 'Inactive household' }}</span>
                <span class="inline-flex rounded-md border border-tubigon/20 bg-tubigon/10 px-2.5 py-1 text-xs font-semibold text-tubigon">{{ number_format($household->residents->count()) }} member{{ $household->residents->count() === 1 ? '' : 's' }}</span>
            </div>
        </div>
    </div>

    <div class="space-y-6 p-6">
        <section>
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-900">Location and Assignment</h3>
                <span class="text-xs font-medium text-slate-400">Registry location and household lead</span>
            </div>
            <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2">
                <div class="min-w-0 bg-white px-4 py-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Household Address</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household->household_address ?: 'Not recorded' }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Barangay</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $barangay?->name ?: 'Not recorded' }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Purok</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $purok?->display_name ?: 'Not recorded' }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Head of Household</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household->headResident?->formal_name ?: 'Not assigned' }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Registered Members</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ number_format($household->residents->count()) }}</dd>
                </div>
            </dl>
        </section>

        <section>
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-sm font-semibold text-slate-900">Environmental and Support Profile</h3>
                <span class="text-xs font-medium text-slate-400">Field survey information</span>
            </div>
            <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2">
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Drinking Water Source</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household->drinking_water_source ?: 'Not recorded' }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Sanitary Toilet</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $toiletStatus }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Garbage Disposal</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household->garbage_disposal_method_label }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Backyard Garden</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ is_null($household->has_backyard_garden) ? 'Not recorded' : ($household->has_backyard_garden ? 'Yes' : 'No') }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Housing Materials</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $household->housing_material_type_label }}</dd>
                </div>
                <div class="min-w-0 bg-white px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Social Aid Beneficiary</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $household->is_social_aid_beneficiary ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>
        </section>
    </div>
</section>

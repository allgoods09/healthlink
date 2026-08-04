@php
    $containerClass = $containerClass ?? 'rounded-[28px] border border-slate-200 bg-white shadow-sm';
    $profile = $resident->socioEconomicProfile;
    $activeFlags = collect([
        'PWD' => (bool) $profile?->is_pwd,
        'OFW' => (bool) $profile?->is_ofw,
        'Solo Parent' => (bool) $profile?->is_solo_parent,
        'OSY' => (bool) $profile?->is_osy,
        'OSC' => (bool) $profile?->is_osc,
        'IP' => (bool) $profile?->is_ip,
    ])->filter();
@endphp

<section class="{{ $containerClass }} overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-tubigon/75">Community Profile</p>
        <h2 class="mt-2 text-lg font-semibold text-slate-900">Socio-Economic Profile</h2>
        <p class="mt-1 text-sm text-slate-500">Education, livelihood, and sector information connected to this resident.</p>
    </div>

    <div class="space-y-6 p-6">
        <dl class="grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2">
            <div class="min-w-0 bg-white px-4 py-3 sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Occupation</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->occupation ?: 'Not recorded' }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Employment Status</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->employment_status ?: 'Not recorded' }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Highest Education</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->highest_education_level ?: 'Not recorded' }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Education Status</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->education_status ?: 'Not recorded' }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-3">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Ethnicity</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->ethnicity ?: 'Not recorded' }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-3 sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Disability Type</dt>
                <dd class="mt-1 break-words text-sm font-medium text-slate-900">{{ $profile?->disability_type ?: 'Not recorded' }}</dd>
            </div>
        </dl>

        <div>
            <p class="text-sm font-semibold text-slate-900">Priority Sectors</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse($activeFlags as $label => $enabled)
                    <span class="inline-flex rounded-md border border-tubigon/20 bg-tubigon/10 px-2.5 py-1 text-xs font-semibold text-tubigon">{{ $label }}</span>
                @empty
                    <span class="text-sm text-slate-500">No priority sector classifications recorded.</span>
                @endforelse
            </div>
        </div>
    </div>
</section>

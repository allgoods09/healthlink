@extends('layouts.portal')

@section('title', 'Campaign Task Detail - HealthLink')
@section('header', 'Campaign Task Detail')
@section('subheader', 'Update completion status and add brief field notes for assigned campaign roster entries.')

@section('content')
    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">Please review the campaign task details.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h2 class="text-lg font-semibold text-slate-900">{{ $assignment->campaign?->title ?? 'Untitled campaign' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $assignment->campaign?->campaign_type_label ?? 'Campaign type not set' }}</p>
            </div>
            <div class="space-y-4 p-6 text-sm text-slate-700">
                <p>Scheduled For: {{ $assignment->campaign?->scheduled_for?->format('M d, Y') ?? 'Not scheduled' }}</p>
                <p>Target: {{ $assignment->target_label }}</p>
                <p>Purok Scope: {{ $assignment->campaign?->assignedPurok?->display_name ?? 'Barangay-wide' }}</p>
                <p>Description: {{ $assignment->campaign?->description ?: 'No description provided.' }}</p>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-semibold text-slate-900">Update Roster Status</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('bhw.campaigns.update', $assignment) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="assignment_status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select name="assignment_status" id="assignment_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach(\App\Models\CommunityCampaignAssignment::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('assignment_status', $assignment->assignment_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="field_notes" class="block text-sm font-medium text-slate-700">Field Notes</label>
                        <textarea name="field_notes" id="field_notes" rows="5" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('field_notes', $assignment->field_notes) }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Save Campaign Update
                    </button>
                </form>
            </div>
        </section>
    </div>
@endsection

@extends('layouts.portal')

@section('title', 'Create BNS Campaign Period - HealthLink')
@section('header', 'Create Campaign Period')
@section('subheader', 'Open a barangay-scoped nutrition cycle before logging OPT+ measurements or later intervention records.')

@section('actions')
    <a href="{{ route('bns.campaign-periods.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Campaigns
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Campaign Details</h2>
            <p class="mt-1 text-sm text-slate-500">Only one active campaign per type is allowed in the same barangay.</p>
        </div>

        <div class="p-6">
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p class="font-semibold">Please review the campaign details.</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('bns.campaign-periods.store') }}" class="space-y-6">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">Campaign Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="OPT+ 2026 Q3">
                    </div>
                    <div>
                        <label for="campaign_type" class="block text-sm font-medium text-slate-700">Campaign Type</label>
                        <select name="campaign_type" id="campaign_type" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach($campaignTypes as $campaignType => $label)
                                <option value="{{ $campaignType }}" @selected(old('campaign_type', \App\Models\NutritionCampaignPeriod::TYPE_OPT_PLUS) === $campaignType)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="starts_on" class="block text-sm font-medium text-slate-700">Start Date</label>
                        <input type="date" name="starts_on" id="starts_on" value="{{ old('starts_on') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                    <div>
                        <label for="ends_on" class="block text-sm font-medium text-slate-700">End Date</label>
                        <input type="date" name="ends_on" id="ends_on" value="{{ old('ends_on') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-slate-700">Notes</label>
                    <textarea name="notes" id="notes" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="Optional field notes, schedule reminders, or coverage details.">{{ old('notes') }}</textarea>
                </div>

                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-tubigon shadow-sm focus:ring-tubigon">
                    <span class="text-sm text-slate-700">Mark this campaign as active immediately</span>
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Save Campaign Period
                    </button>
                    <a href="{{ route('bns.campaign-periods.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

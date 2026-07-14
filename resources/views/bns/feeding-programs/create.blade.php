@extends('layouts.portal')

@section('title', 'Create Feeding Program - HealthLink')
@section('header', 'Create Feeding Program')
@section('subheader', 'Open a feeding batch or supplementary kitchen roster for tracked children in your barangay.')

@section('actions')
    <a href="{{ route('bns.feeding-programs.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
        Back to Programs
    </a>
@endsection

@section('content')
    <div class="mx-auto max-w-4xl rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-900">Program Details</h2>
            <p class="mt-1 text-sm text-slate-500">Link the program to an existing campaign period if you want it tied to a specific field cycle.</p>
        </div>
        <div class="p-6">
            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <p class="font-semibold">Please review the feeding program details.</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('bns.feeding-programs.store') }}" class="space-y-6">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">Program Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon" placeholder="Supplementary Feeding Batch A">
                    </div>
                    <div>
                        <label for="program_status" class="block text-sm font-medium text-slate-700">Program Status</label>
                        <select name="program_status" id="program_status" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                            @foreach($programStatuses as $status => $label)
                                <option value="{{ $status }}" @selected(old('program_status', \App\Models\FeedingProgram::STATUS_PLANNED) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="campaign_period_id" class="block text-sm font-medium text-slate-700">Linked Campaign Period</label>
                    <select name="campaign_period_id" id="campaign_period_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">
                        <option value="">No linked campaign period</option>
                        @foreach($campaignPeriods as $campaignPeriod)
                            <option value="{{ $campaignPeriod->id }}" @selected((string) old('campaign_period_id') === (string) $campaignPeriod->id)>{{ $campaignPeriod->name }}</option>
                        @endforeach
                    </select>
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
                    <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
                    <textarea name="description" id="description" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-tubigon focus:ring-tubigon">{{ old('description') }}</textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center rounded-full bg-tubigon px-5 py-2.5 text-sm font-medium text-white hover:bg-tubigon-hover">
                        Save Feeding Program
                    </button>
                    <a href="{{ route('bns.feeding-programs.index') }}" class="inline-flex items-center rounded-full border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:border-tubigon/20 hover:text-tubigon">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection

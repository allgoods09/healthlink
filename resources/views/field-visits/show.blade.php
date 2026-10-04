@extends($layout)

@section('title', 'Household Visit - HealthLink')
@section('header', 'Household Visit')

@section('actions')
    <a href="{{ route($routePrefix.'.households.show', $visit->household) }}" class="inline-flex rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
        Back to Household
    </a>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Visit Details</h2>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-medium text-gray-500">Household</dt><dd class="mt-1 text-gray-900">#{{ $visit->household->household_no }} · {{ $visit->household->household_address }}</dd></div>
                <div><dt class="font-medium text-gray-500">Location</dt><dd class="mt-1 text-gray-900">{{ $visit->household->purok?->barangay?->name }} / {{ $visit->household->purok?->display_name }}</dd></div>
                <div><dt class="font-medium text-gray-500">Visited</dt><dd class="mt-1 text-gray-900">{{ $visit->visited_at?->format('F d, Y h:i A') }}</dd></div>
                <div><dt class="font-medium text-gray-500">Recorded by</dt><dd class="mt-1 text-gray-900">{{ $visit->recordedBy?->name ?? 'Unknown BHW' }}</dd></div>
            </dl>
            <div class="mt-6 border-t border-gray-200 pt-5">
                <h3 class="text-sm font-semibold text-gray-900">Notes and Observations</h3>
                <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-gray-700">{{ $visit->notes ?: 'No notes recorded for this visit.' }}</p>
            </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Photographs</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                @forelse($photos as $photo)
                    @if($photo['available'])
                        <a href="{{ route($routePrefix.'.visits.photo', [$visit, $photo['index']]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-gray-200">
                            <img src="{{ route($routePrefix.'.visits.photo', [$visit, $photo['index']]) }}" alt="Visit photo {{ $loop->iteration }}" class="h-52 w-full object-cover">
                        </a>
                    @else
                        <div class="flex h-52 items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-500">Photo unavailable</div>
                    @endif
                @empty
                    <p class="text-sm text-gray-500">No photographs were attached to this visit.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

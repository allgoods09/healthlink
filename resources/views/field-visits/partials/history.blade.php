<section class="mt-6 rounded-lg bg-white shadow">
    <div class="border-b border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900">Household Visits</h2>
        <p class="mt-1 text-sm text-gray-500">Read-only field history for this household.</p>
    </div>
    <div class="divide-y divide-gray-200">
        @forelse($visitHistory as $visit)
            <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $visit->visited_at?->format('M d, Y h:i A') }}</p>
                    <p class="mt-1 text-sm text-gray-600">Recorded by {{ $visit->recordedBy?->name ?? 'Unknown BHW' }}</p>
                    <p class="mt-2 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($visit->notes ?: 'No notes recorded.', 150) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $visit->photo_count }} {{ \Illuminate\Support\Str::plural('photo', $visit->photo_count) }}</p>
                </div>
                <a href="{{ route($routePrefix.'.visits.show', $visit) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900">View visit</a>
            </div>
        @empty
            <div class="p-6 text-sm text-gray-500">No household visits have been recorded yet.</div>
        @endforelse
    </div>
    @if($visitHistory->hasPages())
        <div class="border-t border-gray-200 px-6 py-4">{{ $visitHistory->links() }}</div>
    @endif
</section>

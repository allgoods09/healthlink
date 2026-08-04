@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination navigation" class="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">
            Showing
            @if ($paginator->firstItem())
                <span class="font-semibold text-slate-900">{{ $paginator->firstItem() }}</span>
                to
                <span class="font-semibold text-slate-900">{{ $paginator->lastItem() }}</span>
            @else
                <span class="font-semibold text-slate-900">{{ $paginator->count() }}</span>
            @endif
            of <span class="font-semibold text-slate-900">{{ $paginator->total() }}</span> results
        </p>

        <div class="flex flex-wrap items-center gap-1.5" aria-label="Page controls">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-400" aria-disabled="true">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-tubigon/40 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">Previous</a>
            @endif

            <div class="hidden items-center gap-1.5 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-1.5 text-sm font-semibold text-slate-400">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex min-w-9 items-center justify-center rounded-md border border-tubigon bg-tubigon px-2 py-2 text-sm font-bold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Go to page {{ $page }}" class="inline-flex min-w-9 items-center justify-center rounded-md border border-slate-300 bg-white px-2 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-tubigon/40 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-tubigon/40 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">Next</a>
            @else
                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-400" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif

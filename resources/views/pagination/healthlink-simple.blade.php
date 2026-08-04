@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination navigation" class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-400" aria-disabled="true">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-tubigon/40 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">Previous</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-tubigon/40 hover:text-tubigon focus:outline-none focus:ring-2 focus:ring-tubigon/20">Next</a>
        @else
            <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-400" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif

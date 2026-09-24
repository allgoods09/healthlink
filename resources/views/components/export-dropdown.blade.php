@props(['route', 'dataset', 'parameters' => []])

<div class="export-control" data-export-control x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <button type="button" class="filter-modal-trigger export-control-trigger" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Export {{ $dataset }}">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v3h16v-3" /></svg>
        <span>Export</span>
    </button>
    <div class="export-control-menu" x-cloak x-show="open" x-transition.origin.top.right role="menu" aria-label="Export {{ $dataset }}">
        <p class="export-control-heading">{{ $dataset }}</p>
        @foreach(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)', 'pdf' => 'PDF (.pdf)'] as $format => $label)
            <a role="menuitem" href="{{ route($route, array_merge(request()->except('page'), $parameters, ['format' => $format])) }}" class="export-control-option">{{ $label }}</a>
        @endforeach
    </div>
</div>

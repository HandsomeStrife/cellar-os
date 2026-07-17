@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$buttonClasses = 'inline-flex h-9 items-center justify-center gap-1 rounded-md border border-border bg-card px-3 text-sm font-medium text-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-card disabled:hover:text-foreground';
$isCursor = method_exists($paginator, 'getCursorName');
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between gap-3">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <button type="button" disabled class="{{ $buttonClasses }}">
                    <x-icon.chevron-left class="size-4" />
                    Previous
                </button>
            @elseif ($isCursor)
                <button type="button" dusk="previousPage" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->previousCursor()->encode() }}" wire:click="setPage('{{ $paginator->previousCursor()->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $buttonClasses }}">
                    <x-icon.chevron-left class="size-4" />
                    Previous
                </button>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="{{ $buttonClasses }}">
                    <x-icon.chevron-left class="size-4" />
                    Previous
                </button>
            @endif

            {{-- Next Page Link --}}
            @if (! $paginator->hasMorePages())
                <button type="button" disabled class="{{ $buttonClasses }}">
                    Next
                    <x-icon.chevron-right class="size-4" />
                </button>
            @elseif ($isCursor)
                <button type="button" dusk="nextPage" wire:key="cursor-{{ $paginator->getCursorName() }}-{{ $paginator->nextCursor()->encode() }}" wire:click="setPage('{{ $paginator->nextCursor()->encode() }}','{{ $paginator->getCursorName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="{{ $buttonClasses }}">
                    Next
                    <x-icon.chevron-right class="size-4" />
                </button>
            @else
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}" class="{{ $buttonClasses }}">
                    Next
                    <x-icon.chevron-right class="size-4" />
                </button>
            @endif
        </nav>
    @endif
</div>

@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';

$buttonClasses = 'inline-flex h-9 min-w-9 items-center justify-center gap-1 rounded-md border border-border bg-card px-2.5 text-sm font-medium text-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-card disabled:hover:text-foreground';
$currentClasses = 'inline-flex h-9 min-w-9 items-center justify-center rounded-md border border-primary bg-primary px-2.5 font-mono text-sm font-semibold text-primary-foreground shadow-sm';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-wrap items-center justify-between gap-3">
            {{-- Small screens: prev / position / next --}}
            <div class="flex flex-1 items-center justify-between gap-3 sm:hidden">
                <button
                    type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    @disabled($paginator->onFirstPage())
                    dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                    class="{{ $buttonClasses }}"
                    aria-label="{{ __('pagination.previous') }}"
                >
                    <x-icon.chevron-left class="size-4" />
                    Previous
                </button>

                <span class="font-mono text-xs text-muted-foreground">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

                <button
                    type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    @disabled(! $paginator->hasMorePages())
                    dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.before"
                    class="{{ $buttonClasses }}"
                    aria-label="{{ __('pagination.next') }}"
                >
                    Next
                    <x-icon.chevron-right class="size-4" />
                </button>
            </div>

            {{-- Larger screens: result range + page buttons --}}
            <div class="hidden sm:block">
                <p class="text-sm text-muted-foreground">
                    Showing <span class="font-mono text-foreground">{{ number_format($paginator->firstItem()) }}</span>
                    to <span class="font-mono text-foreground">{{ number_format($paginator->lastItem()) }}</span>
                    of <span class="font-mono text-foreground">{{ number_format($paginator->total()) }}</span>
                    results
                </p>
            </div>

            <div class="hidden items-center gap-1.5 sm:flex">
                {{-- Previous --}}
                <button
                    type="button"
                    wire:click="previousPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    @disabled($paginator->onFirstPage())
                    dusk="previousPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                    class="{{ $buttonClasses }}"
                    aria-label="{{ __('pagination.previous') }}"
                >
                    <x-icon.chevron-left class="size-4" />
                </button>

                {{-- Pagination elements --}}
                @foreach ($elements as $element)
                    {{-- "Three dots" separator --}}
                    @if (is_string($element))
                        <span aria-disabled="true" class="inline-flex h-9 min-w-9 items-center justify-center px-1 font-mono text-sm text-muted-foreground">{{ $element }}</span>
                    @endif

                    {{-- Array of links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="{{ $currentClasses }}">{{ $page }}</span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                        class="{{ $buttonClasses }} font-mono"
                                        aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    >
                                        {{ $page }}
                                    </button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                <button
                    type="button"
                    wire:click="nextPage('{{ $paginator->getPageName() }}')"
                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    wire:loading.attr="disabled"
                    @disabled(! $paginator->hasMorePages())
                    dusk="nextPage{{ $paginator->getPageName() == 'page' ? '' : '.' . $paginator->getPageName() }}.after"
                    class="{{ $buttonClasses }}"
                    aria-label="{{ __('pagination.next') }}"
                >
                    <x-icon.chevron-right class="size-4" />
                </button>
            </div>
        </nav>
    @endif
</div>

<div class="max-w-3xl">
    {{-- Page header --}}
    <div class="mb-8 border-b border-border pb-6">
        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-muted-foreground">{{ $breadcrumb }}</p>
        <h1 class="mt-2 font-serif text-3xl font-semibold tracking-tight md:text-4xl">{{ $title }}</h1>
    </div>

    {{-- Mobile section selector (the sticky sidenav is desktop-only). --}}
    <div class="mb-8 md:hidden">
        <label for="mobile-section" class="sr-only">Jump to section</label>
        <select
            id="mobile-section"
            onchange="if(this.value) window.location.href=this.value"
            class="w-full rounded-md border border-input bg-card px-3 py-2 text-sm text-foreground"
        >
            @foreach($sections as $group)
                <optgroup label="{{ $group['title'] }}">
                    @foreach($group['items'] as $slug => $entry)
                        <option value="{{ url('/guide/'.$slug) }}" @selected($slug === $sectionSlug)>{{ $entry['title'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    {{-- Section content --}}
    <article class="guide-prose">
        @include($partial)
    </article>

    {{-- Prev / next --}}
    @php
        $flat = [];
        foreach ($sections as $g) {
            foreach ($g['items'] as $slug => $entry) {
                $flat[] = ['slug' => $slug, 'title' => $entry['title']];
            }
        }
        $currentIndex = collect($flat)->search(fn ($i) => $i['slug'] === $sectionSlug);
        $prev = $currentIndex > 0 ? $flat[$currentIndex - 1] : null;
        $next = $flat[$currentIndex + 1] ?? null;
    @endphp

    <nav class="mt-16 flex flex-wrap justify-between gap-6 border-t border-border pt-8 text-sm">
        <div>
            @if($prev)
                <a href="{{ url('/guide/'.$prev['slug']) }}" wire:navigate class="group inline-flex flex-col text-muted-foreground transition hover:text-primary">
                    <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Previous</span>
                    <span class="mt-1">← {{ $prev['title'] }}</span>
                </a>
            @endif
        </div>
        <div class="text-right">
            @if($next)
                <a href="{{ url('/guide/'.$next['slug']) }}" wire:navigate class="group inline-flex flex-col text-muted-foreground transition hover:text-primary">
                    <span class="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Next</span>
                    <span class="mt-1">{{ $next['title'] }} →</span>
                </a>
            @endif
        </div>
    </nav>
</div>

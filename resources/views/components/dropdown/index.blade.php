@props([
    // Accessible name for the trigger. Also the visible label when `text` is set.
    'label' => 'Actions',
    // Render a labelled button instead of the bare icon trigger.
    'text' => false,
    'icon' => 'ellipsis',
    'align' => 'right',
    'width' => 224,
])

{{--
    Actions menu. The house style is: two actions or fewer render inline, three
    or more collapse in here.

    The menu is positioned `fixed` from the trigger's bounding box rather than
    absolutely inside the trigger's box, because these live in table cells whose
    wrapper is `overflow-x-auto` — an absolutely-positioned menu gets clipped by
    that scroll container. Position is recomputed on open, scroll and resize,
    and the menu flips above the trigger when there isn't room below.
--}}
<div
    x-data="{
        open: false,
        top: 0,
        left: 0,
        width: {{ (int) $width }},
        place() {
            const trigger = this.$refs.trigger.getBoundingClientRect();
            const menu = this.$refs.menu;
            const height = menu ? menu.offsetHeight : 0;
            const gutter = 8;

            const below = trigger.bottom + 4;
            this.top = (height && below + height > window.innerHeight - gutter && trigger.top - height - 4 > gutter)
                ? trigger.top - height - 4
                : below;

            const preferred = '{{ $align }}' === 'right' ? trigger.right - this.width : trigger.left;
            this.left = Math.min(Math.max(gutter, preferred), window.innerWidth - this.width - gutter);
        },
        toggle() {
            if (this.open) {
                this.open = false;

                return;
            }

            this.place();
            this.open = true;
            // Re-place once the menu has a measurable height (for the flip).
            this.$nextTick(() => this.place());
        },
        close(refocus = false) {
            this.open = false;
            if (refocus) {
                this.$refs.trigger.focus();
            }
        },
        move(step) {
            const items = [...this.$refs.menu.querySelectorAll('[role=menuitem]:not([disabled])')];
            if (items.length === 0) {
                return;
            }
            const at = items.indexOf(document.activeElement);
            items[(at + step + items.length) % items.length].focus();
        },
    }"
    x-on:keydown.escape.stop="close(true)"
    x-on:scroll.window="open && place()"
    x-on:resize.window="open && place()"
    {{ $attributes->merge(['class' => 'inline-block']) }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        x-on:keydown.down.prevent="open || toggle(); $nextTick(() => move(1))"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
        @unless($text) aria-label="{{ $label }}" @endunless
        @class([
            'inline-flex items-center justify-center gap-1.5 rounded-md text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40',
            'size-8' => ! $text,
            'h-9 border border-input bg-card px-3 text-foreground shadow-sm' => (bool) $text,
        ])
    >
        <x-dynamic-component :component="'icon.'.$icon" class="size-4" />
        @if($text)
            <span>{{ $label }}</span>
            <x-icon.chevron-down class="size-4" />
        @endif
    </button>

    <div
        x-ref="menu"
        x-show="open"
        x-cloak
        x-on:click.outside="close()"
        x-on:click="close()"
        x-on:keydown.down.prevent="move(1)"
        x-on:keydown.up.prevent="move(-1)"
        x-bind:style="`top:${top}px; left:${left}px; width:${width}px`"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        role="menu"
        aria-orientation="vertical"
        class="fixed z-50 origin-top rounded-md border border-border bg-card py-1 text-left shadow-lg"
    >
        {{ $slot }}
    </div>
</div>

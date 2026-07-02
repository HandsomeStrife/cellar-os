<div>
    @if($total === 0)
        <x-card>
            <x-empty-state icon="map" title="Nothing to map yet" message="Wines with latitude &amp; longitude appear here. Import or add geo-located products to see your global sourcing." />
        </x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-4">
            {{-- Country breakdown --}}
            <x-card title="By country" class="lg:col-span-1">
                <ul class="space-y-1.5 text-sm">
                    @foreach($countries as $country => $count)
                        <li class="flex items-center justify-between">
                            <span class="text-foreground">{{ $country }}</span>
                            <span class="text-muted-foreground tabular-nums">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 border-t border-border pt-3 text-xs text-muted-foreground">{{ number_format($total) }} geo-located wines</p>
            </x-card>

            {{-- Map --}}
            <div class="lg:col-span-3">
                <div
                    wire:ignore
                    x-data="{
                        map: null,
                        points: @js($points),
                        init() {
                            if (! window.L) return;
                            this.map = window.L.map(this.$refs.map, { scrollWheelZoom: false }).setView([25, 5], 2);
                            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap contributors',
                                maxZoom: 18,
                            }).addTo(this.map);

                            const bounds = [];
                            this.points.forEach((p) => {
                                const marker = window.L.circleMarker([p.lat, p.lng], {
                                    radius: 7, color: '#ffffff', weight: 1, fillColor: p.colour, fillOpacity: 0.9,
                                }).addTo(this.map);
                                // Build popup with DOM + textContent so wine names can never inject markup.
                                const el = document.createElement('div');
                                const name = document.createElement('strong');
                                name.textContent = p.name;
                                el.appendChild(name);
                                [p.producer, p.country].forEach((line) => {
                                    if (line) {
                                        el.appendChild(document.createElement('br'));
                                        el.appendChild(document.createTextNode(line));
                                    }
                                });
                                marker.bindPopup(el);
                                bounds.push([p.lat, p.lng]);
                            });
                            if (bounds.length) this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 6 });
                            setTimeout(() => this.map?.invalidateSize(), 200);
                        },
                        destroy() {
                            this.map?.remove();
                            this.map = null;
                        },
                    }"
                    class="overflow-hidden rounded-lg border border-border shadow-sm"
                >
                    <div x-ref="map" class="h-[70vh] w-full bg-secondary"></div>
                </div>
            </div>
        </div>
    @endif
</div>

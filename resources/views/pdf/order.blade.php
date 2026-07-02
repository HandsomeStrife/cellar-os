<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #2b2f36; font-size: 12px; margin: 0; }
        .header { border-bottom: 2px solid #7b1e3b; padding-bottom: 12px; margin-bottom: 20px; }
        .brand { font-size: 22px; font-weight: bold; color: #7b1e3b; }
        .muted { color: #6b7280; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .cols { width: 100%; }
        .cols td { vertical-align: top; width: 50%; padding: 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { text-align: left; border-bottom: 1px solid #d1d5db; padding: 6px 4px; font-size: 11px; text-transform: uppercase; color: #6b7280; }
        table.items td { padding: 6px 4px; border-bottom: 1px solid #eee; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #d1d5db; }
        .notes { margin-top: 20px; padding: 10px; background: #f8f6f2; }
    </style>
</head>
<body>
    <div class="header">
        <table class="cols">
            <tr>
                <td>
                    <div class="brand">CellarOS</div>
                    <div class="muted">Purchase Order</div>
                </td>
                <td class="right">
                    <h1>{{ $order->displayNumber() }}</h1>
                    <div class="muted">{{ $order->created_at?->format('j F Y') }}</div>
                    <div class="muted">Status: {{ $order->status->getLabel() }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="cols">
        <tr>
            <td>
                <strong>Supplier</strong><br>
                {{ $supplier?->name ?? 'N/A' }}<br>
                @if($supplier?->contact)<span class="muted">{{ $supplier->contact }}</span><br>@endif
                @if($supplier?->email)<span class="muted">{{ $supplier->email }}</span><br>@endif
                @if($supplier?->location)<span class="muted">{{ $supplier->location }}</span>@endif
            </td>
            <td>
                <strong>Deliver to</strong><br>
                {{ $venue?->name ?? 'N/A' }}<br>
                @if($venue?->city)<span class="muted">{{ $venue->city }}</span><br>@endif
                @if($venue?->country)<span class="muted">{{ $venue->country }}</span>@endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Wine</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->wine_name }}</td>
                    <td class="right">
                        @if($item->soldByCaseAtOrder())
                            {{ $item->casesAtOrder() }} {{ \Illuminate\Support\Str::plural('case', $item->casesAtOrder()) }} ({{ $item->quantity_units }} btl)
                        @else
                            {{ $item->quantity_units }} btl
                        @endif
                    </td>
                    <td class="right">
                        @if($item->soldByCaseAtOrder())
                            {{ $item->currency_at_order }} {{ number_format((float) $item->casePriceAtOrder(), 2) }} /case
                        @else
                            {{ $item->currency_at_order }} {{ number_format((float) $item->unit_price_at_order, 2) }}
                        @endif
                    </td>
                    <td class="right">{{ $item->currency_at_order }} {{ number_format($item->lineTotal(), 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" class="right">Total</td>
                <td class="right">{{ number_format((float) $order->total, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($order->notes)
        <div class="notes">
            <strong>Notes</strong><br>
            {{ $order->notes }}
        </div>
    @endif
</body>
</html>

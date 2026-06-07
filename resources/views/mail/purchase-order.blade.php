<x-mail::message>
# Purchase Order

@if($supplierName)
Hello {{ $supplierName }},
@endif

Please find attached a purchase order from CellarOS{{ $order->total ? ' totalling £'.number_format((float) $order->total, 2) : '' }}.

The order contains {{ count($order->items) }} {{ \Illuminate\Support\Str::plural('line', count($order->items)) }}. The full breakdown is in the attached PDF.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

@props(['messages' => null])

@if($messages)
    <ul {{ $attributes->merge(['class' => 'mt-1 space-y-0.5 text-sm text-destructive']) }}>
        @foreach((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif

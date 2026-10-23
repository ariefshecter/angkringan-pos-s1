@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-bold text-stone-950 transition hover:bg-amber-300'
            : 'inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold text-stone-500 transition hover:bg-stone-100 hover:text-stone-950';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

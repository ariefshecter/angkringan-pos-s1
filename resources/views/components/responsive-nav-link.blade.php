@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-xl border border-amber-400/30 bg-amber-400 px-4 py-3 text-start text-base font-bold text-stone-950 transition'
            : 'block w-full rounded-xl px-4 py-3 text-start text-base font-semibold text-stone-600 transition hover:bg-stone-100 hover:text-stone-950';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

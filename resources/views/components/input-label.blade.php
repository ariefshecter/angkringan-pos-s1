@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-bold uppercase tracking-wide text-stone-600']) }}>
    {{ $value ?? $slot }}
</label>

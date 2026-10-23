<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button-accent']) }}>
    {{ $slot }}
</button>

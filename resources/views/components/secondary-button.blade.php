<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-button-secondary']) }}>
    {{ $slot }}
</button>

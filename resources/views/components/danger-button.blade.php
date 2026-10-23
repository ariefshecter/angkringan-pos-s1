<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button-danger']) }}>
    {{ $slot }}
</button>

<x-guest-layout>
    <div class="mb-8">
        <p class="ui-eyebrow">Security check</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-stone-950">Konfirmasi password</h1>
        <p class="mt-2 text-sm leading-6 text-stone-500">Area ini dilindungi. Konfirmasi password Anda sebelum melanjutkan.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="space-y-2">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-7">
            <x-primary-button class="w-full">Konfirmasi dan lanjutkan</x-primary-button>
        </div>
    </form>
</x-guest-layout>

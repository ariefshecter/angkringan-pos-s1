<section>
    <header>
        <p class="ui-eyebrow">Security</p>
        <h2 class="mt-2 text-xl font-black tracking-tight text-stone-950">Perbarui password</h2>

        <p class="mt-2 text-sm leading-6 text-stone-500">Gunakan password panjang dan unik untuk menjaga akun operasional tetap aman.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf
        @method('put')

        <div class="space-y-2">
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div class="space-y-2">
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div class="space-y-2">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center gap-4 border-t border-stone-100 pt-5">
            <x-primary-button>Simpan password</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-semibold text-emerald-700"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>

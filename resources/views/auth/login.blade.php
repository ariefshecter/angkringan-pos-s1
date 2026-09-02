<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-2xl bg-amber-500 text-xl font-bold text-white">S1</div>
        <h1 class="text-2xl font-bold text-stone-900">Masuk ke Angkringan S1</h1>
        <p class="mt-1 text-sm text-stone-500">Login Admin dan Kasir tanpa aktivasi perangkat</p>
    </div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" value="Username" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Kata sandi" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Ingat saya</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    Lupa kata sandi?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Masuk
            </x-primary-button>
        </div>
    </form>
    <div class="mt-6 border-t border-stone-200 pt-5 text-center">
        <p class="text-xs text-stone-500">Menyiapkan terminal pelanggan?</p>
        <a href="{{ route('device.activate') }}" class="mt-2 inline-block text-sm font-semibold text-amber-700 hover:text-amber-800">Aktivasi Kiosk Pelanggan</a>
    </div>
</x-guest-layout>

<x-guest-layout>
    <div class="mb-8">
        <p class="ui-eyebrow">Staff access</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-stone-950">Selamat datang kembali</h1>
        <p class="mt-2 text-sm leading-6 text-stone-500">Login Admin dan Kasir tanpa aktivasi perangkat, lalu kelola transaksi dan operasional Angkringan S1.</p>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="space-y-2">
            <x-input-label for="username" value="Username" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5 space-y-2">
            <x-input-label for="password" value="Kata sandi" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-5 flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-stone-600">
                <input id="remember_me" type="checkbox" class="rounded border-stone-300 text-amber-500 shadow-sm focus:ring-amber-500" name="remember">
                <span>Ingat saya</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-semibold text-amber-700 transition hover:text-amber-900 hover:underline" href="{{ route('password.request') }}">Lupa password?</a>
            @endif
        </div>

        <div class="mt-7">
            <x-primary-button class="w-full">Masuk ke dashboard</x-primary-button>
        </div>
    </form>
    <div class="mt-8 rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-3 text-center">
        <p class="text-xs text-stone-500">Menyiapkan terminal pelanggan?</p>
        <a href="{{ route('device.activate') }}" class="mt-1 inline-block text-sm font-bold text-stone-900 hover:text-amber-700">Aktivasi kiosk pelanggan</a>
    </div>
</x-guest-layout>

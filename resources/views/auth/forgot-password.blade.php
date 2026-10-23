<x-guest-layout>
    <div class="mb-8">
        <p class="ui-eyebrow">Account recovery</p>
        <h1 class="mt-3 text-3xl font-black tracking-tight text-stone-950">Pulihkan akses akun</h1>
        <p class="mt-2 text-sm leading-6 text-stone-500">Masukkan email akun admin. Kami akan mengirimkan tautan untuk membuat password baru.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="space-y-2">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-7 space-y-3">
            <x-primary-button class="w-full">Kirim tautan reset</x-primary-button>
            <a href="{{ route('login') }}" class="ui-button-secondary w-full">Kembali ke login</a>
        </div>
    </form>
</x-guest-layout>

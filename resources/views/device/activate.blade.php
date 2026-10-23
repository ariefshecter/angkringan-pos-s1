<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aktivasi Perangkat - Angkringan S1</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="relative grid min-h-screen place-items-center overflow-hidden bg-[#f5f2ec] p-4">
    <div class="pointer-events-none absolute -left-20 top-0 h-72 w-72 rounded-full bg-amber-300/30 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-16 h-80 w-80 rounded-full bg-emerald-200/30 blur-3xl"></div>
    <div class="ui-surface relative w-full max-w-md p-6 sm:p-9">
        <div class="mb-8"><div class="mb-5"><img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="h-auto w-52 max-w-full object-contain"></div><p class="ui-eyebrow">Customer terminal</p><h1 class="mt-3 text-3xl font-black tracking-tight text-stone-950">Aktivasi kiosk</h1><p class="mt-2 text-sm leading-6 text-stone-500">Hubungkan perangkat pelanggan menggunakan kode aktivasi sekali pakai.</p></div>
        @include('partials.flash')
        <form method="POST" action="{{ route('device.activate.store') }}" class="space-y-5">@csrf
            <div class="space-y-2"><x-input-label for="activation_code" value="Kode aktivasi"/><x-text-input id="activation_code" name="activation_code" class="uppercase tracking-[0.2em]" required autofocus autocomplete="off"/><x-input-error :messages="$errors->get('activation_code')" class="mt-2"/></div>
            <button class="ui-button-accent w-full">Aktifkan perangkat</button>
        </form>
        <div class="mt-8 border-t border-stone-200 pt-5 text-center"><p class="text-xs text-stone-500">Admin atau Kasir?</p><a href="{{ route('login') }}" class="mt-1 inline-block text-sm font-bold text-stone-900 hover:text-amber-700">Buka login staf</a></div>
    </div>
</body></html>

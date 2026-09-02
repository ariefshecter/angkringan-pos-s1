<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Aktivasi Perangkat - Angkringan S1</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="grid min-h-screen place-items-center bg-stone-100 p-4">
    <div class="w-full max-w-md rounded-3xl border border-amber-100 bg-white p-8 shadow-xl">
        <div class="mb-6 text-center"><div class="mx-auto mb-3 grid h-16 w-16 place-items-center rounded-2xl bg-amber-500 text-2xl font-bold text-white">S1</div><h1 class="text-2xl font-bold text-stone-900">Aktivasi Kiosk Pelanggan</h1><p class="mt-2 text-sm text-stone-500">Halaman ini hanya untuk perangkat kiosk pelanggan. Admin dan Kasir langsung masuk melalui halaman login.</p></div>
        @include('partials.flash')
        <form method="POST" action="{{ route('device.activate.store') }}" class="space-y-5">@csrf
            <div><x-input-label for="activation_code" value="Kode aktivasi"/><x-text-input id="activation_code" name="activation_code" class="mt-1 block w-full uppercase" required autofocus autocomplete="off"/><x-input-error :messages="$errors->get('activation_code')" class="mt-2"/></div>
            <button class="w-full rounded-xl bg-amber-500 px-4 py-3 font-semibold text-white hover:bg-amber-600">Aktifkan kiosk</button>
        </form>
        <div class="mt-6 border-t border-stone-200 pt-5 text-center"><p class="text-xs text-stone-500">Admin atau Kasir?</p><a href="{{ route('login') }}" class="mt-2 inline-block text-sm font-semibold text-amber-700 hover:text-amber-800">Buka halaman login staf</a></div>
    </div>
</body></html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Angkringan S1') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f5f2ec] font-sans text-stone-900 antialiased">
        <div class="relative grid min-h-screen overflow-hidden lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.82fr)]">
            <div class="pointer-events-none absolute -left-24 -top-32 h-80 w-80 rounded-full bg-amber-300/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 right-1/3 h-96 w-96 rounded-full bg-emerald-200/25 blur-3xl"></div>
            <section class="relative hidden overflow-hidden bg-stone-950 px-12 py-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,rgba(245,158,11,0.28),transparent_32%),radial-gradient(circle_at_90%_90%,rgba(16,185,129,0.16),transparent_30%)]"></div>
                <div class="relative">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3 text-sm font-bold tracking-wide">
                        <img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="block h-auto w-48 max-w-[62vw] object-contain drop-shadow-lg">
                    </a>
                </div>
                <div class="relative max-w-lg">
                    <p class="mb-5 text-xs font-bold uppercase tracking-[0.24em] text-amber-300">Point of sale sederhana</p>
                    <h1 class="text-5xl font-black leading-[1.05] tracking-tight">Kelola pesanan dengan lebih tenang.</h1>
                    <p class="mt-6 max-w-md text-base leading-7 text-stone-300">Satu ruang kerja untuk memantau transaksi, stok menu, dan operasional Angkringan S1.</p>
                    <div class="mt-10 flex items-center gap-3 text-xs font-semibold text-stone-400"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Sistem operasional siap digunakan</div>
                </div>
                <p class="relative text-xs text-stone-500">&copy; {{ now()->year }} Angkringan S1</p>
            </section>
            <main class="relative flex min-h-screen items-center justify-center px-4 py-8 sm:px-8">
                <div class="w-full max-w-md">
                    <div class="mb-8 lg:hidden"><img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="h-auto w-48 max-w-[70vw] object-contain"></div>
                    <div class="ui-surface p-6 sm:p-9">{{ $slot }}</div>
                    <p class="mt-5 text-center text-xs text-stone-500">Terminal operasional Angkringan S1</p>
                </div>
            </main>
        </div>
    </body>
</html>

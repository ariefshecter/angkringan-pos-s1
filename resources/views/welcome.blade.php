<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Angkringan S1') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-white antialiased">
    <main class="relative isolate min-h-screen overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-amber-400/20 blur-3xl"></div>
        <div class="absolute -bottom-48 right-0 h-[32rem] w-[32rem] rounded-full bg-emerald-500/15 blur-3xl"></div>
        <div class="mx-auto flex min-h-screen max-w-7xl flex-col px-5 py-6 sm:px-8 lg:px-12">
            <header class="flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="block h-auto w-52 max-w-[58vw] object-contain drop-shadow-lg">
                </a>
                @if (Route::has('login'))
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="rounded-xl border border-white/20 px-4 py-2.5 text-sm font-bold text-white transition hover:border-amber-300 hover:bg-amber-400 hover:text-stone-950">
                        {{ auth()->check() ? 'Buka dashboard' : 'Login staf' }}
                    </a>
                @endif
            </header>

            <section class="grid flex-1 items-center gap-12 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:py-20">
                <div class="max-w-2xl">
                    <p class="mb-6 text-xs font-bold uppercase tracking-[0.25em] text-amber-300">Point of sale & customer ordering</p>
                    <h1 class="text-5xl font-black leading-[0.98] tracking-tight sm:text-7xl">Rasa hangat.<br><span class="text-amber-400">Operasional rapi.</span></h1>
                    <p class="mt-7 max-w-lg text-base leading-7 text-stone-300 sm:text-lg">Platform pemesanan dan pengelolaan Angkringan S1 untuk membuat setiap transaksi lebih cepat, jelas, dan terukur.</p>
                    <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('device.activate') }}" class="inline-flex items-center justify-center rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-black text-stone-950 shadow-lg shadow-amber-950/30 transition hover:bg-amber-300">Aktivasi kiosk pelanggan</a>
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-white/20 px-6 py-3.5 text-sm font-bold text-white transition hover:border-white/50 hover:bg-white/10">Masuk sebagai staf</a>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-xl">
                    <div class="absolute -inset-5 rounded-[2rem] border border-amber-400/20 bg-amber-400/5 blur-sm"></div>
                    <div class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-white/[0.07] p-5 shadow-2xl backdrop-blur sm:p-7">
                        <div class="flex items-center justify-between border-b border-white/10 pb-5"><div><p class="text-xs font-bold uppercase tracking-widest text-stone-400">Live overview</p><p class="mt-1 font-bold">Operasional hari ini</p></div><span class="flex items-center gap-2 text-xs font-semibold text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Terhubung</span></div>
                        <div class="grid gap-3 py-5 sm:grid-cols-2"><div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-stone-400">Penjualan hari ini</p><p class="mt-2 text-2xl font-black text-amber-300">Rp 2.450.000</p></div><div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-stone-400">Pesanan masuk</p><p class="mt-2 text-2xl font-black">48</p></div></div>
                        <div class="space-y-3"><div class="flex items-center justify-between rounded-2xl border border-white/10 bg-stone-950/40 p-4"><div><p class="text-sm font-bold">Nasi Bakar</p><p class="mt-1 text-xs text-stone-400">Makanan · stok tersedia</p></div><span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-300">24 tersisa</span></div><div class="flex items-center justify-between rounded-2xl border border-white/10 bg-stone-950/40 p-4"><div><p class="text-sm font-bold">Es Kopi Susu</p><p class="mt-1 text-xs text-stone-400">Minuman · stok terbatas</p></div><span class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-bold text-amber-300">6 tersisa</span></div></div>
                    </div>
                </div>
            </section>

            <footer class="flex flex-col gap-3 border-t border-white/10 pt-5 text-xs text-stone-500 sm:flex-row sm:items-center sm:justify-between"><span>Sistem operasional Angkringan S1</span><span>Pesan lebih mudah, layani lebih cepat.</span></footer>
        </div>
    </main>
</body>
</html>

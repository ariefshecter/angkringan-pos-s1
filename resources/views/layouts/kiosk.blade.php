<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Pesan Menu') - Angkringan S1</title>@vite(['resources/css/app.css', 'resources/js/app.js'])@stack('head')</head>
<body class="min-h-screen bg-[#fafafa] text-stone-900">
    <header class="sticky top-0 z-30 border-b border-stone-400 bg-white shadow-sm"><div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8"><a href="{{ route('kiosk.index') }}" class="grid h-10 w-10 place-items-center rounded-full border border-amber-700 bg-white text-sm font-black text-stone-950">S1</a>@hasSection('cart')@yield('cart')@endif</div></header>
    @include('partials.flash')
    <main>@yield('content')</main>
    @stack('scripts')
</body></html>

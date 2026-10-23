<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Pesan Menu') - Angkringan S1</title>@vite(['resources/css/app.css', 'resources/js/app.js'])@stack('head')</head>
<body class="min-h-screen bg-[#f7f3eb] text-stone-900">
    <header class="sticky top-0 z-30 border-b border-stone-200/90 bg-white/95 shadow-[0_8px_24px_-20px_rgba(28,25,23,0.8)] backdrop-blur"><div class="mx-auto flex h-[4.5rem] max-w-[1600px] items-center justify-between px-4 sm:px-6 lg:px-8"><a href="{{ route('kiosk.index') }}" class="group block"><img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="h-auto w-44 max-w-[58vw] object-contain transition group-hover:scale-[1.02]"></a>@hasSection('cart')@yield('cart')@endif</div></header>
    @include('partials.flash')
    <main>@yield('content')</main>
    @stack('scripts')
</body></html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Angkringan S1') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-[#f5f2ec]">
        @include('layouts.navigation')

        <div class="min-h-screen lg:pl-60">
            <header class="sticky top-0 z-30 flex min-h-[4.5rem] items-center justify-between border-b border-stone-200/90 bg-white/95 px-4 py-3 shadow-[0_8px_24px_-24px_rgba(28,25,23,0.8)] backdrop-blur sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" @click="sidebarOpen = true" class="rounded-xl border border-stone-200 bg-stone-50 p-2.5 text-stone-600 transition hover:border-amber-400 hover:text-stone-950 lg:hidden" aria-label="Buka navigasi">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    @isset($header)<div class="min-w-0">{{ $header }}</div>@endisset
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-2xl bg-stone-950 text-amber-400 shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0 17.9 17.9 0 0 1-15 0Z"/></svg>
                    </div>
                    <div class="hidden sm:block"><p class="text-xs font-bold text-stone-900">{{ auth()->user()->name }}</p><p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-stone-500">{{ session('staff_guard') }}</p></div>
                </div>
            </header>

            <main>{{ $slot }}</main>
        </div>

        @auth
            <button id="enable-notifications" type="button" class="fixed bottom-5 left-4 z-[900] rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-xs font-bold text-amber-800 shadow-lg transition hover:border-amber-500 hover:bg-amber-50 lg:left-64">Aktifkan suara</button>
            <script>
                const orderFeedUrl = @json(route('admin.orders.feed'));
                const dashboardUrl = @json(route('dashboard'));
                const enableNotifications = document.getElementById('enable-notifications');
                let knownOrders = JSON.parse(sessionStorage.getItem('knownOrders') || '{}');
                let audioContext;
                enableNotifications.addEventListener('click', async () => {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)(); await audioContext.resume();
                    enableNotifications.textContent = 'Suara aktif'; enableNotifications.disabled = true;
                    window.appToast('Suara notifikasi pesanan telah diaktifkan.', 'success');
                });
                async function checkOrders() {
                    try {
                        const response = await fetch(orderFeedUrl, {headers: {'Accept': 'application/json'}}); if (!response.ok) return;
                        const data = await response.json(); const newOrders = []; const uniqueOrders = new Map();
                        data.orders.forEach(order => {
                            if (uniqueOrders.has(order.order_number)) return;
                            uniqueOrders.set(order.order_number, order);
                        });
                        uniqueOrders.forEach(order => {
                            const key = `${order.order_number}:${order.payment_status}`;
                            if (!knownOrders[key]) { knownOrders[key] = true; newOrders.push(order); }
                        });
                        sessionStorage.setItem('knownOrders', JSON.stringify(knownOrders));
                        if (newOrders.length) {
                            if (audioContext?.state === 'running') { const oscillator = audioContext.createOscillator(); oscillator.connect(audioContext.destination); oscillator.frequency.value = 880; oscillator.start(); oscillator.stop(audioContext.currentTime + 0.25); }
                            const latest = newOrders[0];
                            const message = latest.payment_method === 'cash'
                                ? `${latest.order_number} dari ${latest.customer_name} menunggu konfirmasi tunai.`
                                : `${latest.order_number} dari ${latest.customer_name} sudah dibayar dan menunggu diterima.`;
                            window.appToast(message, 'info', {
                                title: newOrders.length > 1 ? `${newOrders.length} pesanan perlu ditindak` : 'Pesanan perlu ditindak',
                                duration: 8000,
                                url: dashboardUrl,
                                actionLabel: 'Buka dashboard',
                            });
                        }
                    } catch (error) { console.error(error); }
                }
                window.addEventListener('DOMContentLoaded', () => {
                    setInterval(checkOrders, 3000);
                    checkOrders();
                });
            </script>
        @endauth
        @stack('scripts')
    </div>
</body>
</html>

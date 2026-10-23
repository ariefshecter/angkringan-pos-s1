<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-stone-950/40 lg:hidden" @click="sidebarOpen = false"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-stone-200 bg-stone-950 text-white shadow-2xl transition-transform duration-200 lg:translate-x-0">
    <div class="border-b border-white/10 px-6 py-7">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <img src="{{ asset('logo.jpeg') }}" alt="Logo Angkringan S1" class="h-auto w-44 max-w-full object-contain">
        </a>
    </div>

    <nav class="staff-sidebar-scroll min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-7 text-sm">
        <p class="px-4 pb-3 text-[10px] font-bold uppercase tracking-[0.2em] text-stone-500">Workspace</p>
        <a href="{{ route('dashboard') }}" class="block rounded-xl border px-4 py-3 font-semibold transition {{ request()->routeIs('dashboard') ? 'border-amber-400/30 bg-amber-400 text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Dashboard</a>
        @if(auth()->user() instanceof \App\Models\Admin)
            <a href="{{ route('admin.menus.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.menus.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Kelola Menu</a>
            <a href="{{ route('admin.categories.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.categories.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Kategori Menu</a>
            <a href="{{ route('admin.devices.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.devices.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Kelola Perangkat</a>
            <a href="{{ route('admin.kasir.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.kasir.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Kelola Kasir</a>
        @endif
        <a href="{{ route('admin.stocks.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.stocks.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Stok Menu</a>
        <a href="{{ route('admin.orders.index') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.orders.*') && !request()->filled('status') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Riwayat Transaksi</a>
        @if(auth()->user() instanceof \App\Models\Admin)
            <a href="{{ route('admin.reports.sales') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.reports.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Laporan Penjualan</a>
            <a href="{{ route('admin.settings.midtrans.edit') }}" class="block rounded-xl border px-4 py-3 transition {{ request()->routeIs('admin.settings.midtrans.*') ? 'border-amber-400/30 bg-amber-400 font-semibold text-stone-950 shadow-lg shadow-amber-950/20' : 'border-transparent text-stone-300 hover:bg-white/10 hover:text-white' }}">Pengaturan Midtrans</a>
        @endif
    </nav>

    <div class="border-t border-white/10 p-4">
        <a href="{{ route('profile.edit') }}" class="block rounded-xl px-4 py-3 text-xs font-semibold text-stone-300 hover:bg-white/10 hover:text-white">Pengaturan Profil</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="mt-1 w-full rounded-xl px-4 py-3 text-left text-xs font-semibold text-red-300 hover:bg-red-500/15 hover:text-red-200">Keluar</button></form>
    </div>
</aside>

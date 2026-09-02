<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-stone-950/40 lg:hidden" @click="sidebarOpen = false"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-60 flex-col border-r border-stone-300 bg-white transition-transform duration-200 lg:translate-x-0">
    <div class="grid h-36 place-items-center border-b border-stone-200">
        <a href="{{ route('dashboard') }}" class="grid h-20 w-20 place-items-center rounded-full border-2 border-amber-900 text-3xl font-black text-stone-950">S1</a>
    </div>

    <nav class="flex-1 space-y-2 px-3 py-7 text-sm">
        <a href="{{ route('dashboard') }}" class="block rounded-md border px-5 py-3 font-semibold transition {{ request()->routeIs('dashboard') ? 'border-stone-800 bg-white text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Dashboard</a>
        @if(auth()->user() instanceof \App\Models\Admin)
            <a href="{{ route('admin.menus.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.menus.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Kelola Menu</a>
            <a href="{{ route('admin.categories.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.categories.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Kategori Menu</a>
            <a href="{{ route('admin.devices.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.devices.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Kelola Perangkat</a>
            <a href="{{ route('admin.kasir.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.kasir.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Kelola Kasir</a>
        @endif
        <a href="{{ route('admin.stocks.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.stocks.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Stok Menu</a>
        <a href="{{ route('admin.orders.index') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.orders.*') && !request()->filled('status') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Riwayat Transaksi</a>
        @if(auth()->user() instanceof \App\Models\Admin)
            <a href="{{ route('admin.reports.sales') }}" class="block rounded-md border px-5 py-3 transition {{ request()->routeIs('admin.reports.*') ? 'border-stone-800 bg-white font-semibold text-stone-950 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100' }}">Laporan Penjualan</a>
        @endif
    </nav>

    <div class="border-t border-stone-200 p-4">
        <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-xs font-medium text-stone-600 hover:bg-stone-100">Pengaturan Profil</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="mt-1 w-full rounded-lg px-3 py-2 text-left text-xs font-medium text-red-600 hover:bg-red-50">Keluar</button></form>
    </div>
</aside>

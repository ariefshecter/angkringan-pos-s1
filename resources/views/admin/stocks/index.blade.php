<x-app-layout>
    <x-slot name="header">
        <p class="ui-eyebrow">Inventory monitor</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Stok menu</h1>
        <p class="text-sm text-stone-500">Pantau stok yang tersedia secara real-time. Halaman ini hanya dapat digunakan untuk melihat data.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section id="stock-live" class="space-y-5" aria-live="polite" data-feed-url="{{ route('admin.stocks.feed') }}">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                    <span class="flex items-center gap-2 font-semibold">
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                        </span>
                        Stok diperbarui otomatis setiap 2 detik
                    </span>
                    <span id="stock-updated-at">Memuat data stok...</span>
                </div>

                <div class="ui-surface overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-stone-200 text-sm">
                            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                                <tr>
                                    <th class="px-6 py-3">Menu</th>
                                    <th class="px-6 py-3">Kategori</th>
                                    <th class="px-6 py-3">Stok fisik</th>
                                    <th class="px-6 py-3">Reservasi</th>
                                    <th class="px-6 py-3">Stok tersedia</th>
                                    <th class="px-6 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="stock-rows" class="divide-y divide-stone-100">
                                @forelse($menus as $menu)
                                    @include('admin.stocks.partials.row', ['menu' => $menu])
                                @empty
                                    <tr><td colspan="6" class="px-6 py-10 text-center text-stone-500">Belum ada menu.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const live = document.getElementById('stock-live');
                const rows = document.getElementById('stock-rows');
                const updatedAt = document.getElementById('stock-updated-at');
                const feedUrl = live.dataset.feedUrl;

                const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, character => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
                }[character]));

                const renderRows = (menus) => {
                    rows.innerHTML = menus.length ? menus.map(menu => {
                        const available = menu.is_available && menu.available_stock > 0;
                        return `<tr>
                            <td class="px-6 py-4 font-semibold text-stone-900">${escapeHtml(menu.name)}</td>
                            <td class="px-6 py-4 text-stone-600">${escapeHtml(menu.category)}</td>
                            <td class="px-6 py-4">${menu.stock}</td>
                            <td class="px-6 py-4 text-amber-700">${menu.reserved_stock}</td>
                            <td class="px-6 py-4 font-bold ${available ? 'text-emerald-700' : 'text-red-700'}">${menu.available_stock}</td>
                            <td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold ${available ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}">${available ? 'Tersedia' : 'Tidak tersedia'}</span></td>
                        </tr>`;
                    }).join('') : '<tr><td colspan="6" class="px-6 py-10 text-center text-stone-500">Belum ada menu.</td></tr>';
                };

                const refreshStock = async () => {
                    try {
                        const response = await fetch(feedUrl, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                        if (!response.ok) return;
                        const data = await response.json();
                        renderRows(data.menus);
                        updatedAt.textContent = `Pembaruan terakhir: ${new Date(data.updated_at).toLocaleTimeString('id-ID')}`;
                    } catch (error) {
                        updatedAt.textContent = 'Gagal memperbarui data stok';
                    }
                };

                setInterval(refreshStock, 2000);
                refreshStock();
            });
        </script>
    @endpush
</x-app-layout>

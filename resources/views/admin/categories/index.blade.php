<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-bold text-stone-900">Kategori Menu</h1><p class="text-sm text-stone-500">Kelola kelompok makanan dan minuman.</p></x-slot>
    <div class="py-8"><div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
        @include('partials.flash')
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <form class="flex gap-2" method="GET"><input class="rounded-lg border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500" name="search" value="{{ request('search') }}" placeholder="Cari kategori"><button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium">Cari</button></form>
            <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-amber-600">Tambah kategori</a>
        </div>
        <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm"><div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm"><thead class="bg-stone-50 text-left text-xs uppercase text-stone-500"><tr><th class="px-6 py-3">Nama</th><th class="px-6 py-3">Urutan</th><th class="px-6 py-3">Menu</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-stone-100">@forelse($categories as $category)@php
                $status = ! $category->is_active
                    ? ['label' => 'Nonaktif', 'class' => 'bg-stone-100 text-stone-600']
                    : ($category->orderable_menus_count > 0
                        ? ['label' => 'Aktif', 'class' => 'bg-emerald-100 text-emerald-700']
                        : ['label' => 'Stok habis', 'class' => 'bg-red-100 text-red-700']);
            @endphp<tr><td class="px-6 py-4 font-semibold text-stone-900">{{ $category->name }}</td><td class="px-6 py-4">{{ $category->sort_order }}</td><td class="px-6 py-4">{{ $category->menus_count }}</td><td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span></td><td class="px-6 py-4"><div class="flex justify-end gap-3"><a class="font-medium text-amber-700 hover:underline" href="{{ route('admin.categories.edit', $category) }}">Edit</a><form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Kategori {{ $category->name }} akan dihapus. Lanjutkan?" data-confirm-title="Hapus kategori" data-confirm-button="Ya, hapus">@csrf @method('DELETE')<button class="font-medium text-red-600 hover:underline">Hapus</button></form></div></td></tr>@empty<tr><td colspan="5" class="px-6 py-10 text-center text-stone-500">Kategori tidak ditemukan.</td></tr>@endforelse</tbody></table>
        </div></div>{{ $categories->links() }}
    </div></div>
</x-app-layout>

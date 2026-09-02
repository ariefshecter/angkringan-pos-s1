<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-stone-900">Kelola Kasir</h1>
        <p class="text-sm text-stone-500">Atur akun Kasir yang dapat menangani transaksi.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('partials.flash')

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"><p class="text-xs font-medium uppercase text-stone-500">Total Kasir</p><p class="mt-2 text-3xl font-black text-stone-950">{{ $summary['total'] }}</p></div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-xs font-medium uppercase text-emerald-700">Kasir Aktif</p><p class="mt-2 text-3xl font-black text-emerald-700">{{ $summary['active'] }}</p></div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm"><p class="text-xs font-medium uppercase text-red-700">Kasir Nonaktif</p><p class="mt-2 text-3xl font-black text-red-700">{{ $summary['inactive'] }}</p></div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <form method="GET" class="flex flex-1 gap-2 sm:max-w-xl">
                    <input name="search" value="{{ request('search') }}" class="w-full rounded-lg border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Cari nama, username, atau email">
                    <button class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium">Cari</button>
                </form>
                <a href="{{ route('admin.kasir.create') }}" class="rounded-lg bg-amber-500 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-600">Tambah Kasir</a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500"><tr><th class="px-6 py-3">Kasir</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Status</th><th class="px-6 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse($kasirs as $kasir)
                                <tr>
                                    <td class="px-6 py-4"><p class="font-semibold text-stone-950">{{ $kasir->name }}</p><p class="mt-1 text-xs text-stone-500">{{ '@'.$kasir->username }}</p></td>
                                    <td class="px-6 py-4 text-stone-700">{{ $kasir->email }}</td>
                                    <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $kasir->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $kasir->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="px-6 py-4"><div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.kasir.edit', $kasir) }}" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">Edit</a>
                                        <form method="POST" action="{{ route('admin.kasir.toggle', $kasir) }}" data-confirm="Akun {{ $kasir->name }} akan {{ $kasir->is_active ? 'dinonaktifkan' : 'diaktifkan' }}." data-confirm-title="{{ $kasir->is_active ? 'Nonaktifkan Kasir' : 'Aktifkan Kasir' }}" data-confirm-button="Ya, lanjutkan" data-confirm-tone="{{ $kasir->is_active ? 'danger' : 'warning' }}">@csrf @method('PATCH')<button class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $kasir->is_active ? 'border-red-300 text-red-700 hover:bg-red-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">{{ $kasir->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                                        <details class="relative"><summary class="cursor-pointer list-none rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700">Reset Password</summary><form method="POST" action="{{ route('admin.kasir.reset-password', $kasir) }}" class="absolute right-0 z-10 mt-2 w-72 rounded-xl border border-stone-200 bg-white p-4 text-left shadow-xl">@csrf @method('PATCH')<label class="text-xs font-semibold text-stone-600">Password baru<input type="password" name="password" class="mt-1 w-full rounded-lg border-stone-300 text-sm" required></label><label class="mt-3 block text-xs font-semibold text-stone-600">Konfirmasi password<input type="password" name="password_confirmation" class="mt-1 w-full rounded-lg border-stone-300 text-sm" required></label><button class="mt-4 w-full rounded-lg bg-stone-900 px-3 py-2 text-xs font-semibold text-white">Simpan Password</button></form></details>
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-12 text-center text-stone-500">Belum ada akun Kasir.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $kasirs->links() }}
        </div>
    </div>
</x-app-layout>

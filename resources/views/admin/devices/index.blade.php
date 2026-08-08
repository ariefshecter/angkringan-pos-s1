<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-stone-900">Kelola Perangkat</h1>
        <p class="text-sm text-stone-500">Atur perangkat admin dan kiosk yang diizinkan mengakses sistem.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @include('partials.flash')

            @if(session('device_activation'))
                @php($activation = session('device_activation'))
                <div x-data="{ copied: false }" class="rounded-2xl border-2 border-amber-400 bg-amber-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-amber-900">Kode aktivasi {{ $activation['name'] }}</p>
                            <p id="device-activation-code" class="mt-2 font-mono text-2xl font-black tracking-wider text-stone-950">{{ $activation['code'] }}</p>
                            <p class="mt-2 text-xs text-amber-800">Kode hanya ditampilkan sekarang dan hanya dapat digunakan satu kali. Masukkan kode ini pada perangkat yang akan diaktifkan.</p>
                        </div>
                        <button type="button" @click="window.copyText(@js($activation['code'])).then(success => { copied = success; if (! success) window.appShowCopyText(@js($activation['code'])) })" class="shrink-0 rounded-lg bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-700">
                            <span x-text="copied ? 'Tersalin' : 'Salin kode'">Salin kode</span>
                        </button>
                    </div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"><p class="text-xs font-medium uppercase text-stone-500">Total perangkat</p><p class="mt-2 text-3xl font-black text-stone-950">{{ $summary['total'] }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"><p class="text-xs font-medium uppercase text-stone-500">Perangkat aktif</p><p class="mt-2 text-3xl font-black text-emerald-600">{{ $summary['active'] }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"><p class="text-xs font-medium uppercase text-stone-500">Kiosk aktif</p><p class="mt-2 text-3xl font-black text-fuchsia-600">{{ $summary['kiosks'] }}</p></div>
                <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"><p class="text-xs font-medium uppercase text-stone-500">Admin aktif</p><p class="mt-2 text-3xl font-black text-amber-600">{{ $summary['admins'] }}</p></div>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('admin.devices.create') }}" class="rounded-lg bg-amber-500 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-600">Tambah perangkat</a>
            </div>

            <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                            <tr><th class="px-6 py-3">Perangkat</th><th class="px-6 py-3">Jenis</th><th class="px-6 py-3">Koneksi</th><th class="px-6 py-3">Terakhir aktif</th><th class="px-6 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse($devices as $device)
                                @php($isCurrent = $currentDevice?->is($device) ?? false)
                                <tr class="{{ $isCurrent ? 'bg-amber-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <p class="font-semibold text-stone-950">{{ $device->name }}</p>
                                        <p class="mt-1 text-xs text-stone-500">ID perangkat: {{ $device->id }} @if($isCurrent)<span class="font-semibold text-amber-700">• Perangkat ini</span>@endif</p>
                                    </td>
                                    <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $device->type === 'admin' ? 'bg-amber-100 text-amber-800' : 'bg-fuchsia-100 text-fuchsia-800' }}">{{ $device->type === 'admin' ? 'Admin' : 'Kiosk' }}</span></td>
                                    <td class="px-6 py-4">
                                        @if(! $device->is_active)
                                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">Nonaktif</span>
                                        @elseif($device->token_hash)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Terhubung</span>
                                        @elseif($device->activation_code_hash)
                                            <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Menunggu aktivasi</span>
                                        @else
                                            <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-semibold text-stone-600">Belum terhubung</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-stone-800">{{ $device->last_seen_at?->locale('id')->diffForHumans() ?? 'Belum pernah' }}</p>
                                        @if($device->last_ip)<p class="mt-1 text-xs text-stone-500">IP {{ $device->last_ip }}</p>@endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($isCurrent)
                                            <p class="text-right text-xs font-medium text-stone-500">Dilindungi</p>
                                        @else
                                            <div class="flex justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.devices.regenerate', $device) }}" data-confirm="Kode lama tidak dapat digunakan lagi dan koneksi perangkat sebelumnya akan diputus." data-confirm-title="Buat kode aktivasi baru" data-confirm-button="Buat kode baru" data-confirm-tone="warning">
                                                    @csrf
                                                    <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">Kode baru</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.devices.toggle', $device) }}" data-confirm="{{ $device->is_active ? 'Perangkat akan dikeluarkan dan tidak dapat mengakses sistem.' : 'Perangkat akan diaktifkan dan memperoleh kode aktivasi baru.' }}" data-confirm-title="{{ $device->is_active ? 'Nonaktifkan perangkat' : 'Aktifkan perangkat' }}" data-confirm-button="{{ $device->is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan' }}" data-confirm-tone="{{ $device->is_active ? 'danger' : 'warning' }}">
                                                    @csrf @method('PATCH')
                                                    <button class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $device->is_active ? 'border-red-300 text-red-700 hover:bg-red-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-12 text-center text-stone-500">Belum ada perangkat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $devices->links() }}
        </div>
    </div>
</x-app-layout>

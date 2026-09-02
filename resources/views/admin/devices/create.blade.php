<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-bold text-stone-900">Tambah Perangkat</h1>
        <p class="text-sm text-stone-500">Tambahkan terminal kiosk yang digunakan pelanggan untuk memesan.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6">
            <form method="POST" action="{{ route('admin.devices.store') }}" class="space-y-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                @csrf

                <div>
                    <x-input-label for="name" value="Nama perangkat" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" placeholder="Contoh: Kiosk Pelanggan 2" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    <p class="mt-2 text-xs text-stone-500">Gunakan nama yang menunjukkan posisi atau nomor perangkat.</p>
                </div>

                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                    Sistem akan membuat kode aktivasi kiosk yang hanya tampil satu kali dan hanya dapat digunakan pada satu perangkat pelanggan.
                </div>

                <div class="flex justify-end gap-3 border-t border-stone-100 pt-5">
                    <a href="{{ route('admin.devices.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium">Batal</a>
                    <button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Simpan dan buat kode</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

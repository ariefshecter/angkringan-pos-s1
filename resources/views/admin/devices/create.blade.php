<x-app-layout>
    <x-slot name="header">
        <p class="ui-eyebrow">Terminal management</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Tambah perangkat</h1>
        <p class="text-sm text-stone-500">Tambahkan terminal kiosk yang digunakan pelanggan untuk memesan.</p>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6">
            <form method="POST" action="{{ route('admin.devices.store') }}" class="ui-surface space-y-6 p-5 sm:p-7">
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

                <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('admin.devices.index') }}" class="ui-button-secondary">Batal</a>
                    <button class="ui-button-accent">Simpan dan buat kode</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

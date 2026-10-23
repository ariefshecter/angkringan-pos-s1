<x-app-layout>
    <x-slot name="header">
        <p class="ui-eyebrow">Payment gateway settings</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Pengaturan Midtrans</h1>
        <p class="text-sm text-stone-500">Kelola akun dan kredensial Midtrans langsung dari panel admin tanpa merubah file kode atau .env.</p>
    </x-slot>

    <div class="py-8" x-data="{
        testing: false,
        testResult: {{ \Illuminate\Support\Js::from(session('test_result')) }},
        async runTest() {
            this.testing = true;
            this.testResult = null;
            const serverKey = document.getElementById('server_key')?.value || '';
            const isProd = document.querySelector('input[name=\'is_production\']:checked')?.value ?? '0';
            try {
                const res = await fetch('{{ route('admin.settings.midtrans.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        server_key: serverKey,
                        is_production: isProd
                    })
                });
                const data = await res.json();
                this.testResult = data;
            } catch (err) {
                this.testResult = {
                    connected: false,
                    message: 'Gagal menghubungi server lokal: ' + err.message
                };
            } finally {
                this.testing = false;
            }
        }
    }">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 space-y-6">
            @if (session('status'))
                <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-900 shadow-sm">
                    <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px;" class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <!-- Live Test Connection Result Banner -->
            <div x-show="testResult" x-cloak class="rounded-2xl p-5 border transition shadow-sm" :class="testResult?.connected ? 'border-emerald-300 bg-emerald-50/90 text-emerald-950' : 'border-rose-300 bg-rose-50/90 text-rose-950'">
                <div class="flex items-start gap-3">
                    <template x-if="testResult?.connected">
                        <svg width="22" height="22" style="width: 22px; height: 22px; min-width: 22px;" class="flex-shrink-0 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <template x-if="!testResult?.connected">
                        <svg width="22" height="22" style="width: 22px; height: 22px; min-width: 22px;" class="flex-shrink-0 text-rose-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    <div class="space-y-1 flex-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold" x-text="testResult?.connected ? 'Status: Terhubung ke Midtrans' : 'Status: Gagal Terhubung ke Midtrans'"></h3>
                            <button type="button" @click="testResult = null" class="text-xs font-semibold text-stone-400 hover:text-stone-600">&times; Tutup</button>
                        </div>
                        <p class="text-xs leading-relaxed" x-text="testResult?.message"></p>
                        <template x-if="testResult?.warning">
                            <div class="mt-2 rounded-xl bg-amber-100/90 border border-amber-300 p-2.5 text-xs text-amber-950 font-medium" x-text="testResult?.warning"></div>
                        </template>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.midtrans.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Environment Mode -->
                <div class="ui-surface p-5 sm:p-7">
                    <h2 class="text-base font-bold text-stone-900">Mode Lingkungan (Environment)</h2>
                    <p class="mt-1 text-xs text-stone-500">Pilih mode Sandbox untuk pengujian atau Production untuk menerima pembayaran riil.</p>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ !old('is_production', $settings['is_production']) ? 'border-amber-500 bg-amber-50/50 ring-1 ring-amber-500' : 'border-stone-200 hover:bg-stone-50' }}">
                            <input type="radio" name="is_production" value="0" class="mt-1 text-amber-500 focus:ring-amber-500" {{ !old('is_production', $settings['is_production']) ? 'checked' : '' }}>
                            <div>
                                <span class="block text-sm font-bold text-stone-900">Sandbox (Uji Coba)</span>
                                <span class="block text-xs text-stone-500">Menggunakan Midtrans Simulator untuk tes pembayaran gratis tanpa uang asli.</span>
                            </div>
                        </label>

                        <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ old('is_production', $settings['is_production']) ? 'border-amber-500 bg-amber-50/50 ring-1 ring-amber-500' : 'border-stone-200 hover:bg-stone-50' }}">
                            <input type="radio" name="is_production" value="1" class="mt-1 text-amber-500 focus:ring-amber-500" {{ old('is_production', $settings['is_production']) ? 'checked' : '' }}>
                            <div>
                                <span class="block text-sm font-bold text-stone-900">Production (Live)</span>
                                <span class="block text-xs text-stone-500">Menerima pembayaran nyata dari pelanggan toko Angkringan S1.</span>
                            </div>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('is_production')" class="mt-2" />
                </div>

                <!-- API Credentials -->
                <div class="ui-surface p-5 sm:p-7 space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-stone-900">Kredensial API Midtrans</h2>
                            <p class="mt-1 text-xs text-stone-500">Kunci ini dapat diambil dari dashboard Midtrans menu <strong>Settings &rarr; Access Keys</strong>.</p>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="merchant_id" value="Merchant ID" />
                        <x-text-input id="merchant_id" name="merchant_id" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('merchant_id', $settings['merchant_id'])" placeholder="Contoh: G123456789" />
                        <x-input-error :messages="$errors->get('merchant_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="client_key" value="Client Key" />
                        <x-text-input id="client_key" name="client_key" type="text" class="mt-1 block w-full font-mono text-sm" :value="old('client_key', $settings['client_key'])" placeholder="Contoh: SB-Mid-client-..." />
                        <x-input-error :messages="$errors->get('client_key')" class="mt-2" />
                        <p class="mt-1.5 text-xs text-stone-500">Digunakan oleh frontend pelanggan (Kiosk Snap JS).</p>
                    </div>

                    <div x-data="{ showKey: false }">
                        <div class="flex items-center justify-between">
                            <x-input-label for="server_key" value="Server Key" />
                            <button type="button" @click="showKey = !showKey" class="text-xs font-semibold text-amber-600 hover:text-amber-700" x-text="showKey ? 'Sembunyikan' : 'Tampilkan'"></button>
                        </div>
                        <input :type="showKey ? 'text' : 'password'" id="server_key" name="server_key" class="mt-1 block w-full rounded-xl border-stone-300 bg-stone-50/60 px-4 py-3 font-mono text-sm text-stone-900 shadow-sm transition placeholder:text-stone-400 focus:border-amber-500 focus:bg-white focus:ring-amber-500" value="{{ old('server_key', $settings['server_key']) }}" placeholder="Contoh: SB-Mid-server-..." />
                        <x-input-error :messages="$errors->get('server_key')" class="mt-2" />
                        <p class="mt-1.5 text-xs text-stone-500">Rahasia. Digunakan server untuk membuat order dan validasi webhook notifikasi pembayaran.</p>
                    </div>
                </div>

                <!-- Payment Channels -->
                <div class="ui-surface p-5 sm:p-7 space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-stone-900">Metode Pembayaran yang Diaktifkan</h2>
                        <p class="mt-1 text-xs text-stone-500">Centang kanal pembayaran yang ingin ditampilkan pada antarmuka Kiosk pelanggan.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($availablePayments as $channelKey => $channelLabel)
                            @php
                                $isChecked = in_array($channelKey, old('enabled_payments', $settings['enabled_payments'] ?? []));
                            @endphp
                            <label class="flex items-center gap-3 rounded-xl border border-stone-200 p-3.5 transition hover:bg-stone-50">
                                <input type="checkbox" name="enabled_payments[]" value="{{ $channelKey }}" class="rounded border-stone-300 text-amber-500 shadow-sm focus:ring-amber-500" {{ $isChecked ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-stone-800">{{ $channelLabel }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('enabled_payments')" class="mt-2" />
                </div>

                <!-- Webhook URL Info -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 text-sm text-amber-950 space-y-2">
                    <p class="font-bold flex items-center gap-2">
                        <svg width="18" height="18" style="width: 18px; height: 18px; min-width: 18px;" class="h-4 w-4 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        URL Webhook Notifikasi Midtrans:
                    </p>
                    <p class="text-xs text-stone-600">Salin URL ini ke dashboard Midtrans (<em>Settings &rarr; Configuration &rarr; Payment Notification URL</em>):</p>
                    <div class="rounded-lg bg-white/90 p-2.5 font-mono text-xs font-semibold text-stone-800 border border-amber-300 select-all">
                        {{ url('/midtrans/notification') }}
                    </div>
                </div>

                <!-- Actions: Uji Koneksi & Simpan -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2">
                    <button type="button" @click="runTest()" :disabled="testing" class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-400 bg-white px-5 py-3 text-sm font-bold text-amber-900 shadow-sm transition hover:bg-amber-50 disabled:opacity-50">
                        <svg x-show="!testing" width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" class="text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <svg x-show="testing" width="16" height="16" style="width: 16px; height: 16px; min-width: 16px;" class="animate-spin text-amber-600" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="testing ? 'Menguji koneksi ke Midtrans...' : 'Uji Koneksi Midtrans'"></span>
                    </button>

                    <div class="flex flex-col-reverse sm:flex-row gap-3">
                        <a href="{{ route('dashboard') }}" class="ui-button-secondary text-center">Kembali ke Dashboard</a>
                        <button type="submit" class="ui-button-accent">Simpan Pengaturan Midtrans</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

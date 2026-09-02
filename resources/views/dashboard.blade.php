<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-base font-extrabold uppercase tracking-wide text-stone-950">Dashboard {{ auth()->user() instanceof \App\Models\Admin ? 'Admin' : 'Kasir' }}</h1>
    </x-slot>

    <div class="px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            @include('partials.flash')

            <div id="dashboard-live" class="space-y-6" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                    <span class="flex items-center gap-2 font-semibold">
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-600"></span>
                        </span>
                        Dashboard terhubung dan diperbarui otomatis setiap 2 detik
                    </span>
                    <span>Pesanan yang perlu ditindak: <strong>{{ $actionableCount }}</strong></span>
                </div>

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="flex min-h-24 items-center gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:min-h-28 sm:p-5">
                        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full border border-stone-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m4.5 18.75 7.5-7.5 3 3 4.5-4.5M15 9.75h4.5v4.5"/></svg>
                        </div>
                        <div><p class="text-xs text-stone-500">Penjualan Hari Ini</p><p class="mt-1 text-xl font-black text-stone-950">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</p></div>
                    </article>

                    <article class="flex min-h-24 items-center rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:min-h-28 sm:p-5">
                        <div><p class="text-xs text-stone-500">Pesanan Hari Ini</p><p class="mt-2 text-2xl font-black text-stone-950">{{ $todayOrderCount }}</p></div>
                    </article>

                    <article class="flex min-h-24 items-center rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm sm:min-h-28 sm:p-5">
                        <div><p class="text-xs text-amber-800">Pesanan Pending</p><p class="mt-2 text-2xl font-black text-amber-700">{{ $pendingCount }}</p></div>
                    </article>

                    <article class="min-h-24 rounded-xl border border-stone-200 bg-white p-4 shadow-sm sm:min-h-28">
                        <p class="text-center text-[11px] font-medium text-stone-600">Transaksi Tunai vs Digital</p>
                        @php
                            $maxPaymentCount = max(1, $cashOrderCount, $digitalOrderCount);
                        @endphp
                        <div class="mt-3 flex h-16 items-end justify-center gap-8">
                            <div class="text-center"><div class="mx-auto w-7 border border-stone-600 bg-stone-100" style="height: {{ max(8, round(($cashOrderCount / $maxPaymentCount) * 48)) }}px"></div><p class="mt-1 text-[9px]">Tunai ({{ $cashOrderCount }})</p></div>
                            <div class="text-center"><div class="mx-auto w-7 border border-stone-600 bg-stone-800" style="height: {{ max(8, round(($digitalOrderCount / $maxPaymentCount) * 48)) }}px"></div><p class="mt-1 text-[9px]">Digital ({{ $digitalOrderCount }})</p></div>
                        </div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-lg sm:rounded-[2.2rem]">
                    <div class="border-b border-stone-100 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex items-end justify-between gap-4">
                            <div><h2 class="text-base font-extrabold text-stone-950">Transaksi Masuk</h2><p class="mt-1 text-[10px] uppercase text-stone-500">Pesanan baru muncul otomatis</p></div>
                            <a href="{{ route('admin.orders.index') }}" class="shrink-0 text-xs font-semibold text-amber-800 hover:underline">Lihat semua</a>
                        </div>
                    </div>

                    <div class="divide-y divide-stone-100 md:hidden">
                        @forelse($latestTransactions as $transaction)
                            @php
                                $needsAction = ($transaction->payment_method === 'cash' && $transaction->payment_status === 'pending')
                                    || ($transaction->payment_method === 'midtrans' && $transaction->payment_status === 'paid' && ! $transaction->confirmed_at);
                                $statusLabel = match ($transaction->payment_status) {
                                    'paid' => 'Lunas',
                                    'pending' => 'Pending',
                                    'replacing' => 'Mengganti metode pembayaran',
                                    'replaced' => 'Metode pembayaran diganti',
                                    'cancelled' => 'Dibatalkan',
                                    'expired' => 'Kedaluwarsa',
                                    'failed' => 'Gagal',
                                    default => ucfirst($transaction->payment_status),
                                };
                            @endphp
                            <article class="p-4 {{ $needsAction ? 'bg-amber-50/70' : '' }}" data-order-id="{{ $transaction->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0"><p class="truncate font-bold text-stone-950">{{ $transaction->order_number }}</p><p class="mt-1 text-xs text-stone-500">{{ $transaction->created_at->format('d/m/Y H:i:s') }} · {{ $transaction->items_count }} item</p></div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase {{ $transaction->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($transaction->payment_status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-600') }}">{{ $statusLabel }}</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div><p class="text-xs text-stone-500">Pelanggan</p><p class="mt-1 font-semibold">{{ $transaction->customer_name }}</p></div>
                                    <div class="text-right"><p class="text-xs text-stone-500">Total</p><p class="mt-1 font-black">Rp {{ number_format($transaction->total, 0, ',', '.') }}</p></div>
                                    <div class="col-span-2"><p class="text-xs text-stone-500">Pembayaran</p><p class="mt-1 font-semibold uppercase">{{ $transaction->payment_label }}</p></div>
                                </div>
                                <div class="mt-4">@include('admin.orders.partials.dashboard-actions')</div>
                            </article>
                        @empty
                            <div class="px-6 py-14 text-center text-stone-500">Belum ada transaksi masuk.</div>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="min-w-full divide-y divide-stone-200 text-xs">
                            <thead class="bg-stone-50 text-left text-[10px] font-bold uppercase text-stone-700">
                                <tr><th class="px-6 py-4">ID Pesanan</th><th class="px-4 py-4">Waktu</th><th class="px-4 py-4">Pelanggan</th><th class="px-4 py-4">Total</th><th class="px-4 py-4">Metode</th><th class="px-4 py-4">Status</th><th class="px-6 py-4 text-center">Aksi</th></tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @forelse($latestTransactions as $transaction)
                                    @php
                                        $needsAction = ($transaction->payment_method === 'cash' && $transaction->payment_status === 'pending')
                                            || ($transaction->payment_method === 'midtrans' && $transaction->payment_status === 'paid' && ! $transaction->confirmed_at);
                                    @endphp
                                    <tr class="transition hover:bg-stone-50 {{ $needsAction ? 'bg-amber-50/70' : '' }}" data-order-id="{{ $transaction->id }}">
                                        <td class="whitespace-nowrap px-6 py-4 font-medium">{{ $transaction->order_number }}<p class="mt-1 text-[10px] font-normal text-stone-400">{{ $transaction->items_count }} item</p></td>
                                        <td class="whitespace-nowrap px-4 py-4">{{ $transaction->created_at->format('d-m-Y H:i:s') }}</td>
                                        <td class="px-4 py-4">{{ $transaction->customer_name }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 font-semibold">Rp {{ number_format($transaction->total, 0, ',', '.') }}</td>
                                        <td class="px-4 py-4 font-semibold uppercase">{{ $transaction->payment_label }}</td>
                                    <td class="px-4 py-4"><span class="font-bold {{ $transaction->payment_status === 'paid' ? 'text-emerald-700' : ($transaction->payment_status === 'pending' ? 'text-amber-700' : 'text-stone-500') }}">{{ $transaction->payment_status_label }}</span></td>
                                        <td class="min-w-40 px-6 py-3">@include('admin.orders.partials.dashboard-actions')</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-6 py-14 text-center text-stone-500">Belum ada transaksi masuk.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.appLiveRegion('#dashboard-live', {interval: 2000});
            });
        </script>
    @endpush
</x-app-layout>

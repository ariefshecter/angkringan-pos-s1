<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Detail Rekap</h1>
            <p class="mt-0.5 text-sm text-stone-500">Snapshot tersimpan untuk periode {{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6">
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.reports.snapshot.pdf', $report) }}" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-semibold text-white hover:bg-stone-700">Unduh PDF</a>
                <a href="{{ route('admin.reports.snapshots') }}" class="rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-50">Kembali ke Riwayat</a>
            </div>
            <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
                <dl class="grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Periode mulai</dt><dd class="mt-1 font-bold text-stone-900">{{ $report->period_start->locale('id')->translatedFormat('d F Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Periode selesai</dt><dd class="mt-1 font-bold text-stone-900">{{ $report->period_end->locale('id')->translatedFormat('d F Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Total pendapatan</dt><dd class="mt-1 text-2xl font-black text-amber-700">Rp {{ number_format($report->total_revenue, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Transaksi lunas</dt><dd class="mt-1 text-2xl font-black text-stone-900">{{ number_format($report->transaction_count, 0, ',', '.') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Dicatat oleh</dt><dd class="mt-1 font-bold text-stone-900">{{ $report->admin?->name ?? 'Admin tidak tersedia' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-stone-500">Terakhir diperbarui</dt><dd class="mt-1 font-bold text-stone-900">{{ $report->updated_at->locale('id')->translatedFormat('d F Y H:i') }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
</x-app-layout>

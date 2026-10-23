<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-eyebrow">Reports archive</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Detail Rekap</h1>
            <p class="mt-1 text-sm text-stone-500">Snapshot tersimpan untuk periode {{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6">
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.reports.snapshot.pdf', $report) }}" class="ui-button-primary">Unduh PDF</a>
                <a href="{{ route('admin.reports.snapshots') }}" class="ui-button-secondary">Kembali ke riwayat</a>
            </div>
            <section class="ui-surface p-6">
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

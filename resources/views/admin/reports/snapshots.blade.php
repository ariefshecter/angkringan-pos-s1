<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-eyebrow">Reports archive</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-stone-950">Riwayat Rekap</h1>
            <p class="mt-1 text-sm text-stone-500">Snapshot laporan harian dan bulanan yang disimpan Admin.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-end">
                <a href="{{ route('admin.reports.sales') }}" class="ui-button-secondary">Kembali ke laporan</a>
            </div>
            <section class="ui-surface overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                            <tr><th class="px-5 py-3">Periode</th><th class="px-5 py-3">Pencatat</th><th class="px-5 py-3 text-right">Transaksi</th><th class="px-5 py-3 text-right">Pendapatan</th><th class="px-5 py-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse($reports as $report)
                                <tr>
                                    <td class="px-5 py-4 font-semibold text-stone-900">{{ $report->period_start->format('d/m/Y') }} - {{ $report->period_end->format('d/m/Y') }}</td>
                                    <td class="px-5 py-4 text-stone-600">{{ $report->admin?->name ?? 'Admin tidak tersedia' }}</td>
                                    <td class="px-5 py-4 text-right">{{ number_format($report->transaction_count, 0, ',', '.') }}</td>
                                    <td class="px-5 py-4 text-right font-bold">Rp {{ number_format($report->total_revenue, 0, ',', '.') }}</td>
                                    <td class="px-5 py-4 text-right"><a href="{{ route('admin.reports.show', $report) }}" class="font-semibold text-amber-700 hover:text-amber-800">Detail</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center text-stone-500">Belum ada rekap yang disimpan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            {{ $reports->links() }}
        </div>
    </div>
</x-app-layout>

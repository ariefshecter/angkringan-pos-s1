<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Laporan Penjualan</h1>
            <p class="mt-0.5 text-sm text-stone-500">Grafik pendapatan dari transaksi yang sudah lunas.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="flex flex-col gap-5 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-bold text-stone-900">{{ $periodLabel }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $rangeLabel }}</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <form method="GET" action="{{ route('admin.reports.sales') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="text-xs font-semibold text-stone-600">Mode
                            <select name="mode" class="mt-1 block w-full rounded-lg border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500" onchange="this.form.submit()">
                                <option value="daily" @selected($mode === 'daily')>Harian</option>
                                <option value="monthly" @selected($mode === 'monthly')>Bulanan</option>
                            </select>
                        </label>
                        @if($mode === 'monthly')
                            <label class="text-xs font-semibold text-stone-600">Bulan
                                <input type="month" name="month" value="{{ $month }}" class="mt-1 block rounded-lg border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                            </label>
                        @else
                            <label class="text-xs font-semibold text-stone-600">Tanggal
                                <input type="date" name="date" value="{{ $date }}" class="mt-1 block rounded-lg border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                            </label>
                        @endif
                        <button class="rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-stone-700">Tampilkan</button>
                    </form>
                    <form method="POST" action="{{ route('admin.reports.store') }}">
                        @csrf
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-600">Simpan Rekap</button>
                    </form>
                    <a href="{{ route('admin.reports.snapshots') }}" class="rounded-lg border border-stone-300 px-4 py-2.5 text-center text-sm font-semibold text-stone-700 hover:bg-stone-50">Riwayat Rekap</a>
                    <a href="{{ route('admin.reports.pdf', ['mode' => $mode, 'date' => $date, 'month' => $month]) }}" class="rounded-lg border border-stone-300 px-4 py-2.5 text-center text-sm font-semibold text-stone-700 hover:bg-stone-50">Unduh PDF</a>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Total pendapatan</p>
                    <p class="mt-2 text-2xl font-black text-amber-700">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</p>
                </article>
                <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Transaksi lunas</p>
                    <p class="mt-2 text-2xl font-black text-stone-900">{{ number_format($summary['transactions'], 0, ',', '.') }}</p>
                </article>
                <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Rata-rata transaksi</p>
                    <p class="mt-2 text-2xl font-black text-stone-900">Rp {{ number_format($summary['average'], 0, ',', '.') }}</p>
                </article>
                <article class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Tunai / Digital</p>
                    <p class="mt-2 text-2xl font-black text-stone-900">{{ $summary['cash'] }} <span class="text-sm font-semibold text-stone-400">/</span> {{ $summary['digital'] }}</p>
                </article>
            </section>

            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-stone-900">Grafik Pendapatan dan Transaksi</h2>
                    <p class="mt-1 text-xs text-stone-500">Batang menunjukkan pendapatan, garis menunjukkan jumlah transaksi.</p>
                </div>
                <div class="relative h-[340px] w-full sm:h-[420px]">
                    <canvas id="sales-chart" aria-label="Grafik laporan penjualan {{ strtolower($periodLabel) }}" role="img"></canvas>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                <div class="border-b border-stone-200 px-5 py-4">
                    <h2 class="font-bold text-stone-900">Rincian Periode</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                            <tr><th class="px-5 py-3">Periode</th><th class="px-5 py-3 text-right">Transaksi</th><th class="px-5 py-3 text-right">Pendapatan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach($chartRows as $row)
                                <tr>
                                    <td class="px-5 py-3 font-semibold text-stone-800">{{ $row['label'] }}</td>
                                    <td class="px-5 py-3 text-right text-stone-600">{{ $row['orders'] }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-stone-900">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const rows = @json($chartRows->values());
                const rupiah = value => `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;

                new window.Chart(document.getElementById('sales-chart'), {
                    data: {
                        labels: rows.map(row => row.label),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Pendapatan',
                                data: rows.map(row => row.revenue),
                                backgroundColor: 'rgba(245, 158, 11, 0.78)',
                                borderColor: 'rgb(217, 119, 6)',
                                borderWidth: 1,
                                borderRadius: 7,
                                yAxisID: 'revenue',
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Jumlah transaksi',
                                data: rows.map(row => row.orders),
                                borderColor: 'rgb(5, 150, 105)',
                                backgroundColor: 'rgb(5, 150, 105)',
                                pointBackgroundColor: 'white',
                                pointBorderWidth: 3,
                                pointRadius: 4,
                                tension: 0.3,
                                yAxisID: 'orders',
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {mode: 'index', intersect: false},
                        plugins: {
                            legend: {position: 'bottom', labels: {usePointStyle: true, padding: 20}},
                            tooltip: {
                                callbacks: {
                                    label: context => context.dataset.yAxisID === 'revenue'
                                        ? `${context.dataset.label}: ${rupiah(context.parsed.y)}`
                                        : `${context.dataset.label}: ${context.parsed.y}`,
                                },
                            },
                        },
                        scales: {
                            revenue: {
                                beginAtZero: true,
                                position: 'left',
                                ticks: {callback: value => new Intl.NumberFormat('id-ID', {notation: 'compact'}).format(value)},
                                grid: {color: 'rgba(120, 113, 108, 0.12)'},
                            },
                            orders: {
                                beginAtZero: true,
                                position: 'right',
                                ticks: {precision: 0},
                                grid: {drawOnChartArea: false},
                            },
                            x: {grid: {display: false}},
                        },
                    },
                });
            });
        </script>
    @endpush
</x-app-layout>

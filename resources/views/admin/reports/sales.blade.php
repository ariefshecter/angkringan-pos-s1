<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Laporan Penjualan</h1>
            <p class="mt-0.5 text-sm text-stone-500">Grafik pendapatan dari transaksi yang sudah lunas.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-stone-900">{{ $periodLabel }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $rangeLabel }}</p>
                </div>
                <div class="inline-flex w-fit rounded-xl bg-stone-100 p-1">
                    <a href="{{ route('admin.reports.sales', ['period' => 'weekly']) }}" class="rounded-lg px-5 py-2 text-sm font-bold transition {{ $period === 'weekly' ? 'bg-amber-500 text-white shadow-sm' : 'text-stone-600 hover:bg-white' }}">Mingguan</a>
                    <a href="{{ route('admin.reports.sales', ['period' => 'monthly']) }}" class="rounded-lg px-5 py-2 text-sm font-bold transition {{ $period === 'monthly' ? 'bg-amber-500 text-white shadow-sm' : 'text-stone-600 hover:bg-white' }}">Bulanan</a>
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

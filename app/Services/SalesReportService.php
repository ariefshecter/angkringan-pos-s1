<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesReportService
{
    /**
     * @return array{
     *     mode: string,
     *     date: string,
     *     month: string,
     *     start: Carbon,
     *     end: Carbon,
     *     periodLabel: string,
     *     rangeLabel: string,
     *     chartRows: Collection<int, array{label: string, revenue: int, orders: int}>,
     *     summary: array{revenue: int, transactions: int, average: int, cash: int, digital: int}
     * }
     */
    public function calculate(string $mode, ?string $date = null, ?string $month = null): array
    {
        $mode = $mode === 'monthly' ? 'monthly' : 'daily';
        $selected = $mode === 'monthly'
            ? Carbon::createFromFormat('!Y-m', $month ?: now()->format('Y-m'))
            : Carbon::createFromFormat('!Y-m-d', $date ?: now()->toDateString());
        $start = $mode === 'monthly' ? $selected->copy()->startOfMonth() : $selected->copy()->startOfDay();
        $end = $mode === 'monthly' ? $selected->copy()->endOfMonth() : $selected->copy()->endOfDay();
        $transactions = Transaction::query()
            ->latestPerOrder()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get();
        $points = $this->periodPoints($mode, $start);
        $grouped = $transactions->groupBy(
            fn (Transaction $transaction): string => $mode === 'monthly'
                ? $transaction->paid_at->toDateString()
                : $transaction->paid_at->format('H')
        );
        $chartRows = $points->map(function (array $point) use ($grouped): array {
            $transactions = $grouped->get($point['key'], collect());

            return [
                'label' => $point['label'],
                'revenue' => (int) $transactions->sum('total'),
                'orders' => $transactions->count(),
            ];
        });
        $totalRevenue = (int) $transactions->sum('total');
        $transactionCount = $transactions->count();

        return [
            'mode' => $mode,
            'date' => $start->toDateString(),
            'month' => $start->format('Y-m'),
            'start' => $start,
            'end' => $end,
            'periodLabel' => $mode === 'monthly' ? 'Laporan Bulanan' : 'Laporan Harian',
            'rangeLabel' => $mode === 'monthly'
                ? $start->locale('id')->translatedFormat('F Y')
                : $start->locale('id')->translatedFormat('l, d F Y'),
            'chartRows' => $chartRows,
            'summary' => [
                'revenue' => $totalRevenue,
                'transactions' => $transactionCount,
                'average' => $transactionCount > 0 ? (int) round($totalRevenue / $transactionCount) : 0,
                'cash' => $transactions->where('payment_method', 'cash')->count(),
                'digital' => $transactions->where('payment_method', 'midtrans')->count(),
            ],
        ];
    }

    /** @return Collection<int, array{key: string, label: string}> */
    private function periodPoints(string $mode, Carbon $start): Collection
    {
        if ($mode === 'monthly') {
            return collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start): array {
                $date = $start->copy()->day($day);

                return [
                    'key' => $date->toDateString(),
                    'label' => $date->format('d'),
                ];
            });
        }

        return collect(range(0, 23))->map(fn (int $hour): array => [
            'key' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
            'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'.00',
        ]);
    }
}

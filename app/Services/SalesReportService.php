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
     *     endDate: string,
     *     month: string,
     *     endMonth: string,
     *     start: Carbon,
     *     end: Carbon,
     *     periodLabel: string,
     *     rangeLabel: string,
     *     chartRows: Collection<int, array{label: string, revenue: int, orders: int}>,
     *     summary: array{revenue: int, transactions: int, average: int, cash: int, digital: int}
     * }
     */
    public function calculate(
        string $mode,
        ?string $date = null,
        ?string $month = null,
        ?string $endDate = null,
        ?string $endMonth = null,
    ): array {
        $mode = in_array($mode, ['daily', 'monthly'], true) ? $mode : 'daily';
        $selected = $mode === 'monthly'
            ? Carbon::createFromFormat('!Y-m', $month ?: now()->format('Y-m'))
            : Carbon::createFromFormat('!Y-m-d', $date ?: now()->toDateString());
        $selectedEnd = $mode === 'monthly'
            ? Carbon::createFromFormat('!Y-m', $endMonth ?: $selected->format('Y-m'))
            : Carbon::createFromFormat('!Y-m-d', $endDate ?: $selected->toDateString());
        $start = $this->periodStart($mode, $selected);
        $end = $this->periodEnd($mode, $selectedEnd);
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $this->periodEnd($mode, $selected)];
        }
        $transactions = Transaction::query()
            ->latestPerOrder()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get();
        $groupByDay = ! $start->isSameDay($end);
        $points = $this->periodPoints($mode, $start, $end);
        $grouped = $transactions->groupBy(
            fn (Transaction $transaction): string => $this->groupKey($mode, $transaction, $groupByDay)
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
            'date' => $selected->toDateString(),
            'endDate' => $end->toDateString(),
            'month' => $selected->format('Y-m'),
            'endMonth' => $selectedEnd->format('Y-m'),
            'start' => $start,
            'end' => $end,
            'periodLabel' => $mode === 'monthly' ? 'Laporan Bulanan' : 'Laporan Harian',
            'rangeLabel' => match ($mode) {
                'monthly' => $start->locale('id')->translatedFormat('F Y').' - '.$end->locale('id')->translatedFormat('F Y'),
                default => $start->isSameDay($end)
                    ? $start->locale('id')->translatedFormat('l, d F Y')
                    : $start->locale('id')->translatedFormat('d F Y').' - '.$end->locale('id')->translatedFormat('d F Y'),
            },
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
    private function periodPoints(string $mode, Carbon $start, Carbon $end): Collection
    {
        if ($mode === 'monthly') {
            $monthCount = (int) $start->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth());

            return collect(range(0, $monthCount))->map(function (int $month) use ($start): array {
                $date = $start->copy()->addMonths($month);

                return [
                    'key' => $date->format('Y-m'),
                    'label' => $date->locale('id')->translatedFormat('M Y'),
                ];
            });
        }

        if (! $start->isSameDay($end)) {
            $days = $start->diffInDays($end);

            return collect(range(0, $days))->map(function (int $day) use ($start): array {
                $date = $start->copy()->addDays($day);

                return [
                    'key' => $date->toDateString(),
                    'label' => $date->locale('id')->translatedFormat('D d/m'),
                ];
            });
        }

        return collect(range(0, 23))->map(fn (int $hour): array => [
            'key' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
            'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'.00',
        ]);
    }

    private function periodStart(string $mode, Carbon $selected): Carbon
    {
        return $mode === 'monthly'
            ? $selected->copy()->startOfMonth()
            : $selected->copy()->startOfDay();
    }

    private function periodEnd(string $mode, Carbon $selected): Carbon
    {
        return $mode === 'monthly'
            ? $selected->copy()->endOfMonth()
            : $selected->copy()->endOfDay();
    }

    private function groupKey(string $mode, Transaction $transaction, bool $groupByDay): string
    {
        if ($mode === 'monthly') {
            return $transaction->paid_at->format('Y-m');
        }

        if ($groupByDay) {
            return $transaction->paid_at->toDateString();
        }

        return $transaction->paid_at->format('H');
    }
}

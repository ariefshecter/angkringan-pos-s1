<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = $request->string('period')->value() === 'monthly' ? 'monthly' : 'weekly';
        $points = $this->periodPoints($period);
        $start = $points->first()['date']->copy()->startOfDay();
        $end = $period === 'monthly'
            ? $points->last()['date']->copy()->endOfMonth()
            : $points->last()['date']->copy()->endOfDay();

        $transactions = Transaction::query()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at')
            ->get();
        $groupedTransactions = $transactions->groupBy(
            fn (Transaction $transaction): string => $period === 'monthly'
                ? $transaction->paid_at->format('Y-m')
                : $transaction->paid_at->toDateString()
        );
        $chartRows = $points->map(function (array $point) use ($groupedTransactions): array {
            $transactions = $groupedTransactions->get($point['key'], collect());

            return [
                'label' => $point['label'],
                'revenue' => (int) $transactions->sum('total'),
                'orders' => $transactions->count(),
            ];
        });
        $totalRevenue = (int) $transactions->sum('total');
        $transactionCount = $transactions->count();

        return view('admin.reports.sales', [
            'period' => $period,
            'periodLabel' => $period === 'monthly' ? '12 Bulan Terakhir' : '7 Hari Terakhir',
            'rangeLabel' => $start->locale('id')->translatedFormat('d M Y').' – '.$end->locale('id')->translatedFormat('d M Y'),
            'chartRows' => $chartRows,
            'summary' => [
                'revenue' => $totalRevenue,
                'transactions' => $transactionCount,
                'average' => $transactionCount > 0 ? (int) round($totalRevenue / $transactionCount) : 0,
                'cash' => $transactions->where('payment_method', 'cash')->count(),
                'digital' => $transactions->where('payment_method', 'midtrans')->count(),
            ],
        ]);
    }

    private function periodPoints(string $period): Collection
    {
        if ($period === 'monthly') {
            $start = now()->startOfMonth()->subMonths(11);

            return collect(range(0, 11))->map(function (int $offset) use ($start): array {
                $date = $start->copy()->addMonths($offset);

                return [
                    'key' => $date->format('Y-m'),
                    'label' => $date->locale('id')->translatedFormat('M Y'),
                    'date' => $date,
                ];
            });
        }

        $start = now()->startOfDay()->subDays(6);

        return collect(range(0, 6))->map(function (int $offset) use ($start): array {
            $date = $start->copy()->addDays($offset);

            return [
                'key' => $date->toDateString(),
                'label' => $date->locale('id')->translatedFormat('D, d M'),
                'date' => $date,
            ];
        });
    }
}

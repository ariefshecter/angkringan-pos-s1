<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $todayTransactions = Transaction::query()
            ->whereDate('created_at', today())
            ->latestPerOrder();
        $todayPaidTransactions = Transaction::query()
            ->where('payment_status', Transaction::PAYMENT_PAID)
            ->whereDate('paid_at', today())
            ->latestPerOrder();

        return view('dashboard', [
            'categoryCount' => Category::query()->where('is_active', true)->count(),
            'menuCount' => Menu::query()->where('is_available', true)->count(),
            'pendingCount' => Transaction::query()
                ->where('payment_status', Transaction::PAYMENT_PENDING)
                ->latestPerOrder()
                ->count(),
            'actionableCount' => Transaction::query()
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('payment_method', 'cash')
                            ->where('payment_status', Transaction::PAYMENT_PENDING);
                    })->orWhere(function ($query): void {
                        $query->where('payment_method', 'midtrans')
                            ->where('payment_status', Transaction::PAYMENT_PAID)
                            ->whereNull('confirmed_at');
                    });
                })
                ->latestPerOrder()
                ->count(),
            'todayRevenue' => (clone $todayPaidTransactions)->sum('total'),
            'todayOrderCount' => (clone $todayTransactions)->count(),
            'cashOrderCount' => (clone $todayPaidTransactions)->where('payment_method', 'cash')->count(),
            'digitalOrderCount' => (clone $todayPaidTransactions)->where('payment_method', 'midtrans')->count(),
            'latestTransactions' => Transaction::query()
                ->latestPerOrder()
                ->withCount('items')
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}

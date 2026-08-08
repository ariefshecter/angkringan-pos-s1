<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $transactions = Transaction::query()
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('payment_status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('transactions'));
    }

    public function show(Transaction $transaction): View
    {
        return view('admin.orders.show', ['transaction' => $transaction->load(['items', 'handler'])]);
    }

    public function feed(OrderService $orders): JsonResponse
    {
        Transaction::query()
            ->where('payment_status', Transaction::PAYMENT_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->each(fn (Transaction $transaction) => $orders->cancel($transaction, Transaction::PAYMENT_EXPIRED));

        $transactions = Transaction::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('payment_method', 'cash')
                        ->where('payment_status', Transaction::PAYMENT_PENDING);
                })
                    ->orWhere(function ($query): void {
                        $query->where('payment_method', 'midtrans')
                            ->where('payment_status', Transaction::PAYMENT_PAID)
                            ->whereNull('confirmed_at');
                    });
            })
            ->latest()
            ->limit(20)
            ->get(['id', 'order_number', 'customer_name', 'total', 'payment_method', 'payment_status', 'created_at']);

        return response()->json(['orders' => $transactions]);
    }

    public function confirmCash(Transaction $transaction, OrderService $orders): RedirectResponse
    {
        $orders->confirmCash($transaction, request()->user());

        return redirect()->route('admin.orders.receipt', ['transaction' => $transaction, 'auto' => 1]);
    }

    public function acceptDigital(Transaction $transaction, OrderService $orders): RedirectResponse
    {
        $orders->acceptPaid($transaction, request()->user());

        return redirect()->route('admin.orders.receipt', ['transaction' => $transaction, 'auto' => 1]);
    }

    public function cancel(Transaction $transaction, OrderService $orders): RedirectResponse
    {
        $orders->cancel($transaction);

        return redirect()->route('admin.orders.index')->with('success', 'Pesanan dibatalkan dan reservasi stok dilepas.');
    }

    public function receipt(Transaction $transaction): View
    {
        abort_unless($transaction->payment_status === Transaction::PAYMENT_PAID, 404);

        return view('admin.orders.receipt', ['transaction' => $transaction->load(['items', 'handler'])]);
    }

    public function markPrinted(Transaction $transaction): JsonResponse
    {
        $transaction->update(['printed_at' => now()]);

        return response()->json(['printed_at' => $transaction->printed_at?->toIso8601String()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Transaction;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function create(CartService $cart): View|RedirectResponse
    {
        if ($cart->items()->isEmpty()) {
            return redirect()->route('kiosk.index')->with('error', 'Keranjang masih kosong.');
        }

        return view('kiosk.checkout', [
            'items' => $cart->items(),
            'total' => $cart->total(),
            'bankChannels' => config('services.midtrans.bank_channels'),
        ]);
    }

    public function store(Request $request, CartService $cart, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'qris', 'bank_transfer'])],
            'payment_channel' => [
                Rule::requiredIf($request->input('payment_method') === 'bank_transfer'),
                'nullable',
                Rule::in(array_keys(config('services.midtrans.bank_channels', []))),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($cart->items()->isEmpty()) {
            return redirect()->route('kiosk.index')->with('error', 'Keranjang masih kosong.');
        }

        $cartItems = $cart->items();

        try {
            /** @var Device $device */
            $device = $request->attributes->get('device');
            $transaction = $orders->create($device, $data, $cartItems);
            $cart->clear();

            return redirect()->route('kiosk.orders.show', $transaction->order_number);
        } catch (Throwable $exception) {
            report($exception);
            $cart->restoreItems($cartItems);

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function show(Request $request, string $orderNumber): View
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->with('items')
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('kiosk.status', [
            'transaction' => $transaction,
            'clientKey' => config('services.midtrans.client_key'),
            'snapUrl' => config('services.midtrans.is_production')
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ]);
    }

    public function status(Request $request, string $orderNumber): array
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return [
            'payment_status' => $transaction->payment_status,
            'order_status' => $transaction->order_status,
            'confirmed' => filled($transaction->confirmed_at),
        ];
    }

    public function cancel(Request $request, string $orderNumber, OrderService $orders, CartService $cart): RedirectResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if ($transaction->payment_status !== Transaction::PAYMENT_PENDING) {
            return back()->with('error', 'Pesanan yang sudah diproses tidak dapat dibatalkan.');
        }

        try {
            $transaction = $orders->cancelForPaymentChange($transaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        if ($transaction->payment_status !== Transaction::PAYMENT_CANCELLED) {
            return back()->with('error', 'Pesanan tidak dapat dibatalkan karena statusnya sudah berubah.');
        }

        $cart->restoreFromTransaction($transaction);

        return redirect()
            ->route('kiosk.checkout.create')
            ->withInput([
                'customer_name' => $transaction->customer_name,
                'notes' => $transaction->notes,
            ])
            ->with('success', 'Pembayaran dibatalkan. Silakan pilih metode pembayaran lain.');
    }

    public function cancelOrder(Request $request, string $orderNumber, OrderService $orders, CartService $cart): RedirectResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        if ($transaction->payment_status !== Transaction::PAYMENT_PENDING) {
            return back()->with('error', 'Pesanan yang sudah diproses tidak dapat dibatalkan.');
        }

        try {
            $transaction = $orders->cancelForPaymentChange($transaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        if ($transaction->payment_status !== Transaction::PAYMENT_CANCELLED) {
            return back()->with('error', 'Pesanan tidak dapat dibatalkan karena statusnya sudah berubah.');
        }

        $cart->clear();

        return redirect()
            ->route('kiosk.index')
            ->with('success', 'Pesanan berhasil dibatalkan dan stok telah dikembalikan.');
    }
}

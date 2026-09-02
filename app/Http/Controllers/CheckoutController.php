<?php

namespace App\Http\Controllers;

use App\Exceptions\MidtransCancellationPendingException;
use App\Exceptions\PaymentInitializationException;
use App\Models\Device;
use App\Models\Transaction;
use App\Services\CartService;
use App\Services\GuestSessionService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function create(CartService $cart, GuestSessionService $guests): View|RedirectResponse
    {
        $editingTransaction = null;
        if ($orderNumber = $guests->paymentChangeOrder()) {
            /** @var Device $device */
            $device = request()->attributes->get('device');
            $editingTransaction = Transaction::query()
                ->with('items.menu')
                ->where('device_id', $device->id)
                ->where('order_number', $orderNumber)
                ->where('user_session_id', $guests->id())
                ->whereIn('payment_status', [
                    Transaction::PAYMENT_PENDING,
                    Transaction::PAYMENT_REPLACING,
                    Transaction::PAYMENT_FAILED,
                    Transaction::PAYMENT_EXPIRED,
                    Transaction::PAYMENT_CANCELLED,
                    Transaction::PAYMENT_REPLACED,
                ])
                ->where(function ($query): void {
                    $query->where('payment_status', '!=', Transaction::PAYMENT_CANCELLED)
                        ->orWhereNull('cancelled_at');
                })
                ->first();

            if (! $editingTransaction) {
                $guests->completePaymentChange();

                return redirect()->route('kiosk.index')->with('error', 'Pesanan pembayaran tidak dapat diperbarui.');
            }
        }

        if (! $editingTransaction && ($orderNumber = $guests->activeOrder())) {
            /** @var Device $device */
            $device = request()->attributes->get('device');
            $activeTransaction = Transaction::query()
                ->where('device_id', $device->id)
                ->where('order_number', $orderNumber)
                ->where('user_session_id', $guests->id())
                ->first();

            if ($activeTransaction?->isActiveForGuest()) {
                return redirect()->route('kiosk.orders.show', $orderNumber);
            }

            $guests->clearActiveOrder();
        }

        $items = $editingTransaction
            ? $editingTransaction->items
                ->map(fn ($item): ?array => $item->menu ? [
                    'menu' => $item->menu,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ] : null)
                ->filter()
                ->values()
            : $cart->items();

        if ($items->isEmpty()) {
            return redirect()->route('kiosk.index')->with('error', 'Keranjang masih kosong.');
        }

        return view('kiosk.checkout', [
            'items' => $items,
            'total' => $editingTransaction?->total ?? $cart->total(),
            'bankChannels' => config('services.midtrans.bank_channels'),
            'editingOrder' => $editingTransaction?->order_number,
            'editingCustomerName' => $editingTransaction?->customer_name,
            'editingNotes' => $editingTransaction?->notes,
            'editingPaymentMethod' => $editingTransaction
                ? ($editingTransaction->payment_method === 'cash'
                    ? 'cash'
                    : ($editingTransaction->payment_channel === 'other_qris' ? 'qris' : 'bank_transfer'))
                : null,
            'editingPaymentChannel' => $editingTransaction?->payment_channel,
        ]);
    }

    public function store(Request $request, CartService $cart, OrderService $orders, GuestSessionService $guests): RedirectResponse
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

        $paymentChangeOrder = $guests->paymentChangeOrder();
        if (! $paymentChangeOrder && $cart->items()->isEmpty()) {
            return redirect()->route('kiosk.index')->with('error', 'Keranjang masih kosong.');
        }

        $cartItems = $paymentChangeOrder ? collect() : $cart->items();

        try {
            /** @var Device $device */
            $device = $request->attributes->get('device');
            $customer = $guests->sync($data['customer_name']);
            $transaction = $paymentChangeOrder
                ? Transaction::query()
                    ->where('device_id', $device->id)
                    ->where('order_number', $paymentChangeOrder)
                    ->where('user_session_id', $guests->id())
                    ->first()
                : null;

            if ($paymentChangeOrder && ! $transaction) {
                $guests->completePaymentChange();

                return back()->withInput()->with('error', 'Pesanan pembayaran tidak ditemukan.');
            }

            if ($transaction) {
                $transaction = $orders->updatePayment($transaction, $data);
            } else {
                $data['user_session_id'] = $customer->session_id;
                $activeOrder = $guests->activeOrder()
                    ?: Transaction::query()
                        ->where('device_id', $device->id)
                        ->where('user_session_id', $customer->session_id)
                        ->latestPerOrder()
                        ->activeForGuest()
                        ->latest('id')
                        ->value('order_number');
                if ($activeOrder) {
                    return redirect()
                        ->route('kiosk.orders.show', $activeOrder)
                        ->with('error', 'Selesaikan atau batalkan pesanan aktif sebelum membuat pesanan baru.');
                }

                $transaction = $orders->create($device, $data, $cartItems);
                $guests->markActiveOrder($transaction->order_number);
            }

            if (! $paymentChangeOrder) {
                $cart->clear();
            }
            $guests->completePaymentChange();

            return redirect()->route('kiosk.orders.show', $transaction->order_number);
        } catch (MidtransCancellationPendingException $exception) {
            report($exception);
            $guests->markActiveOrder($exception->transaction->order_number);

            return redirect()
                ->route('kiosk.orders.show', $exception->transaction->order_number)
                ->with('error', $exception->getMessage());
        } catch (PaymentInitializationException $exception) {
            report($exception);
            $guests->markActiveOrder($exception->transaction->order_number);

            return redirect()
                ->route('kiosk.orders.show', $exception->transaction->order_number)
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            if ($paymentChangeOrder) {
                $guests->markActiveOrder($paymentChangeOrder);
            } else {
                $cart->restoreItems($cartItems);
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function show(Request $request, string $orderNumber, GuestSessionService $guests): View
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->with('items')
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->where('user_session_id', $guests->id())
            ->firstOrFail();

        return view('kiosk.status', [
            'transaction' => $transaction,
            'clientKey' => config('services.midtrans.client_key'),
            'snapUrl' => config('services.midtrans.is_production')
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js',
        ]);
    }

    public function status(Request $request, string $orderNumber, GuestSessionService $guests): array
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->where('user_session_id', $guests->id())
            ->firstOrFail();

        if ($transaction->isTerminalForGuest()
            && $transaction->user_session_id === $guests->id()
            && ! $guests->isPaymentChange($transaction->order_number)
        ) {
            $guests->rotate();
        }

        return [
            'payment_status' => $transaction->payment_status,
            'order_status' => $transaction->order_status,
            'confirmed' => filled($transaction->confirmed_at),
            'explicitly_cancelled' => $transaction->isExplicitlyCancelled(),
        ];
    }

    public function changePayment(
        Request $request,
        string $orderNumber,
        OrderService $orders,
        GuestSessionService $guests,
    ): RedirectResponse {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->where('user_session_id', $guests->id())
            ->firstOrFail();

        if (! $transaction->canBeEdited()) {
            return redirect()
                ->route('kiosk.orders.show', $transaction->order_number)
                ->with('error', 'Metode pembayaran tidak dapat diganti karena status pesanan sudah berubah.');
        }

        if ($transaction->payment_status === Transaction::PAYMENT_REPLACING) {
            $guests->markActiveOrder($transaction->order_number);

            return redirect()
                ->route('kiosk.orders.show', $transaction->order_number)
                ->with('error', 'Metode pembayaran sedang diproses. Silakan tunggu beberapa saat.');
        }

        $guests->markActiveOrder($transaction->order_number);

        // Keep status polling from rotating the guest session while the gateway call is in flight.
        $guests->preserveForPaymentChange($transaction->order_number);

        try {
            $transaction = $orders->preparePaymentChange($transaction);
        } catch (Throwable $exception) {
            report($exception);

            $current = $transaction->fresh();
            if (! $current || ! in_array($current->payment_status, [
                Transaction::PAYMENT_PENDING,
                Transaction::PAYMENT_REPLACING,
            ], true)) {
                $guests->completePaymentChange();
            }

            return redirect()
                ->route('kiosk.orders.show', $transaction->order_number)
                ->with('error', $exception->getMessage());
        }

        if (! in_array($transaction->payment_status, [
            Transaction::PAYMENT_PENDING,
            Transaction::PAYMENT_REPLACING,
        ], true)) {
            $guests->completePaymentChange();

            return redirect()
                ->route('kiosk.orders.show', $transaction->order_number)
                ->with('error', 'Metode pembayaran tidak dapat diganti karena status pesanan sudah berubah.');
        }

        return redirect()
            ->route('kiosk.checkout.create')
            ->withInput([
                'customer_name' => $transaction->customer_name,
                'notes' => $transaction->notes,
            ])
            ->with('success', 'Pesanan yang sama siap diperbarui. Silakan pilih metode pembayaran baru.');
    }

    public function cancelOrder(Request $request, string $orderNumber, OrderService $orders, CartService $cart, GuestSessionService $guests): RedirectResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $transaction = Transaction::query()
            ->where('device_id', $device->id)
            ->where('order_number', $orderNumber)
            ->where('user_session_id', $guests->id())
            ->firstOrFail();

        if (! $transaction->canBeEdited()) {
            return back()->with('error', 'Pesanan yang sudah diproses tidak dapat dibatalkan.');
        }

        if ($transaction->payment_status === Transaction::PAYMENT_REPLACING) {
            return back()->with('error', 'Metode pembayaran sedang diproses. Silakan tunggu beberapa saat.');
        }

        try {
            $transaction = $orders->cancelPending($transaction);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        if ($transaction->payment_status !== Transaction::PAYMENT_CANCELLED) {
            return back()->with('error', 'Pesanan tidak dapat dibatalkan karena statusnya sudah berubah.');
        }

        $cart->clear();
        $guests->rotate();

        return redirect()
            ->route('kiosk.index')
            ->with('success', 'Pesanan berhasil dibatalkan dan stok telah dikembalikan.');
    }
}

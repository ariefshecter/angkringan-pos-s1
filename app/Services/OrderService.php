<?php

namespace App\Services;

use App\Models\CartReservation;
use App\Models\Device;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(private readonly MidtransService $midtrans) {}

    /** @throws Throwable */
    public function create(Device $device, array $data, Collection $cartItems): Transaction
    {
        $transaction = DB::transaction(function () use ($device, $data, $cartItems): Transaction {
            $isDigital = $data['payment_method'] !== 'cash';
            $resolvedItems = collect();
            $total = 0;

            foreach ($cartItems as $cartItem) {
                $menu = Menu::query()->lockForUpdate()->findOrFail($cartItem['menu']->id);
                $quantity = (int) $cartItem['quantity'];
                $reservation = CartReservation::query()
                    ->where('device_id', $device->id)
                    ->where('menu_id', $menu->id)
                    ->lockForUpdate()
                    ->first();

                if (! $menu->is_available || ! $reservation || $reservation->expires_at->isPast() || $reservation->quantity !== $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Reservasi stok {$menu->name} sudah berubah atau kedaluwarsa. Silakan perbarui keranjang.",
                    ]);
                }

                if ($menu->reserved_stock < $quantity || $menu->stock < $menu->reserved_stock) {
                    throw ValidationException::withMessages([
                        'cart' => "Stok {$menu->name} tidak konsisten. Silakan hubungi admin.",
                    ]);
                }

                $subtotal = $menu->price * $quantity;
                $total += $subtotal;
                $resolvedItems->push(compact('menu', 'quantity', 'subtotal'));
                $reservation->delete();
            }

            $transaction = Transaction::query()->create([
                'order_number' => $this->newOrderNumber(),
                'device_id' => $device->id,
                'customer_name' => $data['customer_name'],
                'subtotal' => $total,
                'total' => $total,
                'payment_method' => $isDigital ? 'midtrans' : 'cash',
                'payment_channel' => match ($data['payment_method']) {
                    'qris' => 'other_qris',
                    'bank_transfer' => $data['payment_channel'] ?? null,
                    default => null,
                },
                'payment_status' => Transaction::PAYMENT_PENDING,
                'order_status' => Transaction::ORDER_NEW,
                'expires_at' => now()->addMinutes($isDigital ? 15 : 30),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($resolvedItems as $item) {
                $transaction->items()->create([
                    'menu_id' => $item['menu']->id,
                    'menu_name' => $item['menu']->name,
                    'price' => $item['menu']->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $transaction;
        });

        if ($transaction->payment_method === 'midtrans') {
            try {
                if ($transaction->payment_channel === 'other_qris') {
                    $transaction->update(['midtrans_snap_token' => $this->midtrans->createSnapToken($transaction)]);
                } else {
                    $virtualAccount = $this->midtrans->createVirtualAccount($transaction);
                    $transaction->update([
                        'midtrans_transaction_id' => $virtualAccount['transaction_id'],
                        'midtrans_payment_type' => $virtualAccount['payment_type'],
                        'midtrans_bank' => $virtualAccount['bank'],
                        'midtrans_va_number' => $virtualAccount['va_number'],
                    ]);
                }
            } catch (Throwable $exception) {
                try {
                    $this->midtrans->cancelTransaction($transaction);
                } catch (Throwable $cancelException) {
                    report($cancelException);
                }
                $this->cancel($transaction);
                throw $exception;
            }
        }

        return $transaction->fresh('items');
    }

    public function confirmCash(Transaction $transaction, User $user): Transaction
    {
        return DB::transaction(function () use ($transaction, $user): Transaction {
            $locked = Transaction::query()->with('items')->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->payment_method !== 'cash' || $locked->payment_status !== Transaction::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Transaksi tunai ini tidak dapat dikonfirmasi.']);
            }

            $this->commitReservedStock($locked);
            $locked->update([
                'payment_status' => Transaction::PAYMENT_PAID,
                'order_status' => Transaction::ORDER_PROCESSING,
                'handled_by' => $user->id,
                'paid_at' => now(),
                'confirmed_at' => now(),
            ]);

            return $locked;
        });
    }

    public function markPaid(Transaction $transaction, array $paymentData): Transaction
    {
        return DB::transaction(function () use ($transaction, $paymentData): Transaction {
            $locked = Transaction::query()->with('items')->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->payment_status === Transaction::PAYMENT_PAID) {
                return $locked;
            }

            if ($locked->payment_status !== Transaction::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Status transaksi tidak dapat diubah menjadi lunas.']);
            }

            $this->commitReservedStock($locked);
            $locked->update([
                'payment_status' => Transaction::PAYMENT_PAID,
                'paid_at' => now(),
                'midtrans_transaction_id' => $paymentData['transaction_id'] ?? null,
                'midtrans_payment_type' => $paymentData['payment_type'] ?? null,
            ]);

            return $locked;
        });
    }

    public function acceptPaid(Transaction $transaction, User $user): Transaction
    {
        if ($transaction->payment_status !== Transaction::PAYMENT_PAID) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran digital belum diterima.']);
        }

        $transaction->update([
            'handled_by' => $user->id,
            'order_status' => Transaction::ORDER_PROCESSING,
            'confirmed_at' => now(),
        ]);

        return $transaction;
    }

    public function cancel(Transaction $transaction, string $status = Transaction::PAYMENT_CANCELLED): Transaction
    {
        return DB::transaction(function () use ($transaction, $status): Transaction {
            $locked = Transaction::query()->with('items')->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->payment_status !== Transaction::PAYMENT_PENDING) {
                return $locked;
            }

            foreach ($locked->items as $item) {
                $menu = Menu::query()->lockForUpdate()->find($item->menu_id);
                if ($menu) {
                    $menu->decrement('reserved_stock', min($menu->reserved_stock, $item->quantity));
                }
            }

            $locked->update([
                'payment_status' => $status,
                'order_status' => Transaction::ORDER_CANCELLED,
                'cancelled_at' => now(),
            ]);

            return $locked;
        });
    }

    public function cancelForPaymentChange(Transaction $transaction): Transaction
    {
        if ($transaction->payment_method === 'midtrans') {
            $this->midtrans->cancelTransaction($transaction);
        }

        return $this->cancel($transaction);
    }

    private function commitReservedStock(Transaction $transaction): void
    {
        foreach ($transaction->items as $item) {
            $menu = Menu::query()->lockForUpdate()->find($item->menu_id);
            if (! $menu || $menu->stock < $item->quantity || $menu->reserved_stock < $item->quantity) {
                throw ValidationException::withMessages(['stock' => "Stok {$item->menu_name} tidak konsisten."]);
            }

            $menu->decrement('stock', $item->quantity);
            $menu->decrement('reserved_stock', $item->quantity);
        }
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'S1-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Transaction::query()->where('order_number', $number)->exists());

        return $number;
    }
}

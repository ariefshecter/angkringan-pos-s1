<?php

namespace App\Services;

use App\Exceptions\MidtransCancellationPendingException;
use App\Exceptions\PaymentInitializationException;
use App\Models\Admin;
use App\Models\CartReservation;
use App\Models\Device;
use App\Models\Kasir;
use App\Models\Menu;
use App\Models\Transaction;
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
            [$items, $total] = $this->resolveCartItems($device, $cartItems);
            $isDigital = $data['payment_method'] !== 'cash';
            $orderNumber = $this->newOrderNumber();

            $transaction = Transaction::query()->create([
                'order_number' => $orderNumber,
                // The first attempt keeps the public ID for compatibility. Retries
                // receive a separate gateway ID when the same row is reused.
                'midtrans_order_id' => $isDigital ? $orderNumber : null,
                'user_session_id' => $data['user_session_id'],
                'device_id' => $device->id,
                'customer_name' => $data['customer_name'],
                'subtotal' => $total,
                'total' => $total,
                'payment_method' => $isDigital ? 'midtrans' : 'cash',
                'payment_channel' => $this->paymentChannel($data),
                'payment_status' => Transaction::PAYMENT_PENDING,
                'order_status' => Transaction::ORDER_NEW,
                'reservation_active' => true,
                'expires_at' => now()->addMinutes($isDigital ? 15 : 30),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $transaction->items()->create([
                    'menu_id' => $item['menu']->id,
                    'menu_name' => $item['menu']->name,
                    'price' => $item['menu']->price,
                    'quantity' => $item['quantity'],
                    'reserved_quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $transaction;
        });

        if ($transaction->payment_method !== 'midtrans') {
            return $transaction->fresh('items');
        }

        try {
            $this->initializeMidtrans($transaction);
        } catch (Throwable $exception) {
            $current = $transaction->fresh('items');

            if ($current->payment_status === Transaction::PAYMENT_PAID) {
                return $current;
            }

            try {
                $this->midtrans->cancelTransaction($current);
            } catch (Throwable $cancelException) {
                throw new MidtransCancellationPendingException($current, $cancelException);
            }

            throw new PaymentInitializationException($this->markPaymentAttemptFailed($current), $exception);
        }

        return $transaction->fresh('items');
    }

    /**
     * Update payment details on the existing logical order. No transaction row is
     * inserted and the public order number remains unchanged.
     *
     * @throws Throwable
     */
    public function updatePayment(Transaction $transaction, array $data): Transaction
    {
        $updated = DB::transaction(function () use ($transaction, $data): Transaction {
            $locked = $this->lockWithItems($transaction);

            if (! $locked->canBeEdited()) {
                throw ValidationException::withMessages([
                    'payment' => 'Pesanan sudah diproses dan tidak dapat diperbarui.',
                ]);
            }

            if (! $locked->reservation_active) {
                $this->reserveTransactionStock($locked);
            }

            $isDigital = $data['payment_method'] !== 'cash';
            $gatewayHistory = $this->appendGatewayHistory(
                $locked->midtrans_order_id,
                $locked->midtrans_order_id_history,
            );
            $attributes = [
                'customer_name' => $data['customer_name'],
                'payment_method' => $isDigital ? 'midtrans' : 'cash',
                'payment_channel' => $this->paymentChannel($data),
                'payment_status' => Transaction::PAYMENT_PENDING,
                'order_status' => Transaction::ORDER_NEW,
                'reservation_active' => true,
                'expires_at' => now()->addMinutes($isDigital ? 15 : 30),
                'paid_at' => null,
                'cancelled_at' => null,
                'confirmed_at' => null,
                'printed_at' => null,
                'admin_id' => null,
                'kasir_id' => null,
                'notes' => $data['notes'] ?? null,
                'midtrans_order_id' => null,
                'midtrans_order_id_history' => $gatewayHistory,
                ...$this->clearGatewayAttributes(),
            ];

            // Each new gateway attempt gets a unique ID while the POS order ID stays fixed.
            if ($isDigital) {
                $attributes['midtrans_order_id'] = $this->newGatewayOrderId();
                $attributes['midtrans_order_id_history'] = $this->appendGatewayHistory(
                    $locked->midtrans_order_id,
                    $locked->midtrans_order_id_history,
                );
            }

            $locked->update($attributes);

            return $locked->fresh('items');
        });

        if ($updated->payment_method !== 'midtrans') {
            return $updated;
        }

        try {
            $this->initializeMidtrans($updated);
        } catch (Throwable $exception) {
            $current = $updated->fresh('items');

            if ($current->payment_status === Transaction::PAYMENT_PAID) {
                return $current;
            }

            try {
                $this->midtrans->cancelTransaction($current);
            } catch (Throwable $cancelException) {
                throw new MidtransCancellationPendingException($current, $cancelException);
            }

            throw new PaymentInitializationException($this->markPaymentAttemptFailed($current), $exception);
        }

        return $updated->fresh('items');
    }

    public function confirmCash(Transaction $transaction, Admin|Kasir $user): Transaction
    {
        return DB::transaction(function () use ($transaction, $user): Transaction {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_method !== 'cash' || $locked->payment_status !== Transaction::PAYMENT_PENDING) {
                throw ValidationException::withMessages(['payment' => 'Transaksi tunai ini tidak dapat dikonfirmasi.']);
            }

            if (! $locked->reservation_active) {
                $this->reserveTransactionStock($locked);
            }

            $this->commitReservedStock($locked);
            $locked->update([
                'payment_status' => Transaction::PAYMENT_PAID,
                'order_status' => Transaction::ORDER_PROCESSING,
                ...$this->staffAttributes($user),
                'paid_at' => now(),
                'confirmed_at' => now(),
            ]);

            return $locked;
        });
    }

    public function markPaid(Transaction $transaction, array $paymentData): Transaction
    {
        return DB::transaction(function () use ($transaction, $paymentData): Transaction {
            $locked = $this->lockWithItems($transaction);

            if (! $this->callbackMatches($locked, $paymentData)
                || $locked->payment_method !== 'midtrans') {
                return $locked;
            }

            if ($locked->payment_status === Transaction::PAYMENT_PAID
                || $locked->isExplicitlyCancelled()) {
                return $locked;
            }

            if (! in_array($locked->payment_status, [
                Transaction::PAYMENT_PENDING,
                Transaction::PAYMENT_REPLACING,
                Transaction::PAYMENT_CANCELLING,
            ], true)) {
                return $locked;
            }

            if (! $locked->reservation_active) {
                $this->reserveTransactionStock($locked);
            }

            $locked->update([
                'payment_status' => Transaction::PAYMENT_PAID,
                'order_status' => in_array($locked->order_status, [
                    Transaction::ORDER_REPLACING,
                    Transaction::ORDER_CANCELLING,
                ], true) ? Transaction::ORDER_NEW : $locked->order_status,
                'cancelled_at' => null,
                'paid_at' => now(),
                'midtrans_transaction_id' => $paymentData['transaction_id'] ?? $locked->midtrans_transaction_id,
                'midtrans_payment_type' => $paymentData['payment_type'] ?? $locked->midtrans_payment_type,
            ]);
            $this->commitReservedStock($locked);

            return $locked;
        });
    }

    public function acceptPaid(Transaction $transaction, Admin|Kasir $user): Transaction
    {
        return DB::transaction(function () use ($transaction, $user): Transaction {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($locked->payment_method !== 'midtrans' || $locked->payment_status !== Transaction::PAYMENT_PAID) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran digital belum diterima.']);
            }

            if ($locked->confirmed_at !== null) {
                return $locked;
            }

            $locked->update([
                ...$this->staffAttributes($user),
                'order_status' => Transaction::ORDER_PROCESSING,
                'confirmed_at' => now(),
            ]);

            return $locked;
        });
    }

    /** Finalize an explicit customer/staff cancellation. */
    public function cancel(Transaction $transaction, string $status = Transaction::PAYMENT_CANCELLED): Transaction
    {
        return DB::transaction(function () use ($transaction, $status): Transaction {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_status === Transaction::PAYMENT_PAID
                || $locked->isExplicitlyCancelled()) {
                return $locked;
            }

            $this->releaseReservedStock($locked);
            $locked->update([
                'payment_status' => $status,
                'order_status' => Transaction::ORDER_CANCELLED,
                'cancelled_at' => now(),
                'expires_at' => null,
                ...$this->retireGatewayAttempt($locked),
            ]);

            return $locked;
        });
    }

    /** Apply a signed gateway status without creating or cancelling a logical order. */
    public function cancelFromMidtrans(
        Transaction $transaction,
        string $status,
        array $paymentData = [],
    ): Transaction {
        return DB::transaction(function () use ($transaction, $status, $paymentData): Transaction {
            $locked = $this->lockWithItems($transaction);

            if (! $this->callbackMatches($locked, $paymentData)
                || $locked->payment_method !== 'midtrans') {
                return $locked;
            }

            if ($locked->payment_status === Transaction::PAYMENT_PAID
                || $locked->isExplicitlyCancelled()) {
                return $locked;
            }

            if ($locked->payment_status === Transaction::PAYMENT_CANCELLING) {
                return in_array($status, [
                    Transaction::PAYMENT_CANCELLED,
                    Transaction::PAYMENT_FAILED,
                    Transaction::PAYMENT_EXPIRED,
                ], true)
                    ? ($status === Transaction::PAYMENT_CANCELLED
                        ? $this->finalizeCancellationLocked($locked, $paymentData['order_id'] ?? null)
                        : $this->finalizeRetryablePayment($locked, $status, $paymentData['order_id'] ?? null))
                    : $locked;
            }

            if ($locked->payment_status === Transaction::PAYMENT_REPLACING) {
                // A gateway outcome during a payment edit closes only the old
                // attempt. The same logical order remains available for checkout.
                $nextStatus = $status === Transaction::PAYMENT_CANCELLED
                    ? Transaction::PAYMENT_PENDING
                    : $status;
                $locked->update([
                    'payment_status' => $nextStatus,
                    'order_status' => Transaction::ORDER_NEW,
                    'cancelled_at' => null,
                    'expires_at' => null,
                    ...$this->retireGatewayAttempt($locked, $paymentData['order_id'] ?? null),
                ]);

                return $locked->fresh('items');
            }

            // Gateway failure is an attempt outcome, not an explicit order cancellation.
            if (in_array($status, [
                Transaction::PAYMENT_EXPIRED,
                Transaction::PAYMENT_FAILED,
                Transaction::PAYMENT_CANCELLED,
            ], true)) {
                $this->releaseReservedStock($locked);
            }

            $locked->update([
                'payment_status' => $status,
                'order_status' => Transaction::ORDER_NEW,
                'cancelled_at' => null,
                'expires_at' => null,
                ...$this->retireGatewayAttempt($locked, $paymentData['order_id'] ?? null),
            ]);

            return $locked;
        });
    }

    /** @throws Throwable */
    public function cancelPending(
        Transaction $transaction,
        string $status = Transaction::PAYMENT_CANCELLED,
    ): Transaction {
        [$claimed, $originalStatus, $shouldCancelGateway] = DB::transaction(function () use ($transaction): array {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_status === Transaction::PAYMENT_PAID
                || $locked->isExplicitlyCancelled()) {
                return [$locked, null, false];
            }

            if (! $locked->canBeEdited()) {
                return [$locked, null, false];
            }

            $originalStatus = $locked->payment_status;
            $shouldCancelGateway = $locked->payment_method === 'midtrans'
                && ($this->hasActiveGatewayAttempt($locked)
                    || filled($locked->midtrans_order_id)
                    || ($originalStatus === Transaction::PAYMENT_PENDING && blank($locked->midtrans_order_id)));
            $locked->update([
                'payment_status' => Transaction::PAYMENT_CANCELLING,
                'order_status' => Transaction::ORDER_CANCELLING,
            ]);

            return [$locked, $originalStatus, $shouldCancelGateway];
        });

        if ($originalStatus === null) {
            return $claimed;
        }

        try {
            if ($shouldCancelGateway) {
                $this->midtrans->cancelTransaction($claimed);
            }
        } catch (Throwable $exception) {
            $current = $this->lockCurrent($claimed);

            if ($current->payment_status === Transaction::PAYMENT_PAID
                || $current->isExplicitlyCancelled()) {
                return $current;
            }

            $this->restorePaymentState($claimed, $originalStatus);

            throw $exception;
        }

        if ($status !== Transaction::PAYMENT_CANCELLED) {
            return $this->finalizeRetryablePayment($claimed, $status);
        }

        return $this->cancel($claimed, $status);
    }

    /** Expire a payment attempt while keeping the same logical order retryable. */
    public function expirePayment(Transaction $transaction): Transaction
    {
        return $this->cancelPending($transaction, Transaction::PAYMENT_EXPIRED);
    }

    /** Claim an existing order for the payment-method edit form. */
    public function preparePaymentChange(Transaction $transaction): Transaction
    {
        [$claimed, $ownsClaim, $previousStatus, $shouldCancelGateway] = DB::transaction(function () use ($transaction): array {
            $locked = $this->lockWithItems($transaction);

            if (! $locked->canBeEdited()) {
                return [$locked, false, null, false];
            }

            if ($locked->payment_status === Transaction::PAYMENT_REPLACING) {
                return [$locked, false, Transaction::PAYMENT_REPLACING, false];
            }

            $previousStatus = $locked->payment_status;
            $shouldCancelGateway = $previousStatus === Transaction::PAYMENT_PENDING
                && $locked->payment_method === 'midtrans'
                && ($this->hasActiveGatewayAttempt($locked) || filled($locked->midtrans_order_id));
            $locked->update([
                'payment_status' => Transaction::PAYMENT_REPLACING,
                'order_status' => Transaction::ORDER_REPLACING,
                'cancelled_at' => null,
            ]);

            return [$locked, true, $previousStatus, $shouldCancelGateway];
        });

        if (! $ownsClaim) {
            return $claimed;
        }

        try {
            if ($shouldCancelGateway) {
                $this->midtrans->cancelTransaction($claimed);
            }
        } catch (Throwable $exception) {
            $current = $this->lockCurrent($claimed);

            if ($current->payment_status !== Transaction::PAYMENT_REPLACING) {
                return $current;
            }

            $this->restorePaymentState($claimed, $previousStatus ?: Transaction::PAYMENT_PENDING);

            throw $exception;
        }

        return DB::transaction(function () use ($claimed): Transaction {
            $locked = $this->lockWithItems($claimed);

            if ($locked->payment_status !== Transaction::PAYMENT_REPLACING) {
                return $locked;
            }

            $attributes = [
                'payment_status' => Transaction::PAYMENT_PENDING,
                'order_status' => Transaction::ORDER_NEW,
                'cancelled_at' => null,
                'expires_at' => null,
                ...$this->retireGatewayAttempt($locked),
            ];

            $locked->update($attributes);

            return $locked;
        });
    }

    /** Compatibility alias for older service callers. */
    public function replacePending(Transaction $transaction): Transaction
    {
        return $this->preparePaymentChange($transaction);
    }

    private function finalizeCancellationLocked(Transaction $transaction, ?string $callbackOrderId = null): Transaction
    {
        $this->releaseReservedStock($transaction);
        $transaction->update([
            'payment_status' => Transaction::PAYMENT_CANCELLED,
            'order_status' => Transaction::ORDER_CANCELLED,
            'cancelled_at' => now(),
            'expires_at' => null,
            ...$this->retireGatewayAttempt($transaction, $callbackOrderId),
        ]);

        return $transaction;
    }

    private function finalizeRetryablePayment(
        Transaction $transaction,
        string $status,
        ?string $callbackOrderId = null,
    ): Transaction {
        return DB::transaction(function () use ($transaction, $status, $callbackOrderId): Transaction {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_status === Transaction::PAYMENT_PAID
                || $locked->isExplicitlyCancelled()) {
                return $locked;
            }

            $this->releaseReservedStock($locked);
            $locked->update([
                'payment_status' => $status,
                'order_status' => Transaction::ORDER_NEW,
                'cancelled_at' => null,
                'expires_at' => null,
                ...$this->retireGatewayAttempt($locked, $callbackOrderId),
            ]);

            return $locked;
        });
    }

    private function restorePaymentState(Transaction $transaction, string $status): Transaction
    {
        return DB::transaction(function () use ($transaction, $status): Transaction {
            $locked = $this->lockWithItems($transaction);

            if (in_array($locked->payment_status, [
                Transaction::PAYMENT_REPLACING,
                Transaction::PAYMENT_CANCELLING,
            ], true)) {
                $locked->update([
                    'payment_status' => $status,
                    'order_status' => Transaction::ORDER_NEW,
                    'cancelled_at' => null,
                ]);
            }

            return $locked;
        });
    }

    private function resetPaymentAttempt(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_status !== Transaction::PAYMENT_PAID
                && ! $locked->isExplicitlyCancelled()) {
                $locked->update([
                    'payment_status' => Transaction::PAYMENT_PENDING,
                    'order_status' => Transaction::ORDER_NEW,
                    'expires_at' => null,
                    ...$this->retireGatewayAttempt($locked),
                ]);
            }

            return $locked;
        });
    }

    private function markPaymentAttemptFailed(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction): Transaction {
            $locked = $this->lockWithItems($transaction);

            if ($locked->payment_status !== Transaction::PAYMENT_PAID
                && ! $locked->isExplicitlyCancelled()) {
                $locked->update([
                    'payment_status' => Transaction::PAYMENT_FAILED,
                    'order_status' => Transaction::ORDER_NEW,
                    'expires_at' => null,
                    ...$this->retireGatewayAttempt($locked),
                ]);
            }

            return $locked;
        });
    }

    private function lockCurrent(Transaction $transaction): Transaction
    {
        return DB::transaction(fn (): Transaction => $this->lockWithItems($transaction));
    }

    private function initializeMidtrans(Transaction $transaction): void
    {
        $transaction = $transaction->fresh('items');
        if (! $transaction->midtrans_order_id) {
            $transaction->update(['midtrans_order_id' => $this->newGatewayOrderId()]);
            $transaction->refresh();
        }

        $gatewayOrderId = $transaction->midtrans_order_id;
        if ($transaction->payment_channel === 'other_qris') {
            $token = $this->midtrans->createSnapToken($transaction);

            DB::transaction(function () use ($transaction, $gatewayOrderId, $token): void {
                $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
                if ($locked->midtrans_order_id === $gatewayOrderId
                    && ! in_array($locked->payment_status, [
                        Transaction::PAYMENT_PAID,
                        Transaction::PAYMENT_CANCELLED,
                    ], true)) {
                    $locked->update(['midtrans_snap_token' => $token]);
                }
            });

            return;
        }

        $virtualAccount = $this->midtrans->createVirtualAccount($transaction);

        DB::transaction(function () use ($transaction, $gatewayOrderId, $virtualAccount): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->midtrans_order_id === $gatewayOrderId
                && ! in_array($locked->payment_status, [
                    Transaction::PAYMENT_PAID,
                    Transaction::PAYMENT_CANCELLED,
                ], true)) {
                $locked->update([
                    'midtrans_transaction_id' => $virtualAccount['transaction_id'],
                    'midtrans_payment_type' => $virtualAccount['payment_type'],
                    'midtrans_bank' => $virtualAccount['bank'],
                    'midtrans_va_number' => $virtualAccount['va_number'],
                ]);
            }
        });
    }

    private function callbackMatches(Transaction $transaction, array $paymentData): bool
    {
        $callbackOrderId = (string) ($paymentData['order_id'] ?? '');

        if ($callbackOrderId === '') {
            return true;
        }

        if (filled($transaction->midtrans_order_id)) {
            return $callbackOrderId === $transaction->midtrans_order_id;
        }

        if (in_array($callbackOrderId, $transaction->midtrans_order_id_history ?: [], true)) {
            return false;
        }

        return $callbackOrderId === $transaction->order_number;
    }

    private function hasActiveGatewayAttempt(Transaction $transaction): bool
    {
        return $transaction->payment_method === 'midtrans'
            && (filled($transaction->midtrans_snap_token)
                || filled($transaction->midtrans_transaction_id));
    }

    /** @param array<int, string>|null $history */
    private function appendGatewayHistory(?string $gatewayOrderId, ?array $history): ?array
    {
        if (blank($gatewayOrderId)) {
            return $history ?: null;
        }

        $history = array_values(array_filter($history ?: [], fn ($id): bool => filled($id)));
        if (! in_array($gatewayOrderId, $history, true)) {
            $history[] = $gatewayOrderId;
        }

        return $history;
    }

    /** Retire the current gateway attempt while retaining its callback tombstone. */
    private function retireGatewayAttempt(Transaction $transaction, ?string $callbackOrderId = null): array
    {
        $history = $this->appendGatewayHistory(
            $transaction->midtrans_order_id,
            $transaction->midtrans_order_id_history,
        );
        $history = $this->appendGatewayHistory($callbackOrderId, $history);

        return [
            'midtrans_order_id' => null,
            'midtrans_order_id_history' => $history,
            ...$this->clearGatewayAttributes(),
        ];
    }

    private function lockWithItems(Transaction $transaction): Transaction
    {
        $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
        $items = $locked->items()
            ->orderBy('menu_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($item->reserved_quantity === null) {
                $reserved = $locked->reservation_active
                    && in_array($locked->payment_status, [
                        Transaction::PAYMENT_PENDING,
                        Transaction::PAYMENT_REPLACING,
                        Transaction::PAYMENT_CANCELLING,
                    ], true)
                    ? $item->quantity
                    : 0;
                $item->update(['reserved_quantity' => $reserved]);
                $item->reserved_quantity = $reserved;
            }
        }

        $locked->setRelation('items', $items);

        return $locked;
    }

    /** @return array{0: Collection, 1: int} */
    private function resolveCartItems(Device $device, Collection $cartItems): array
    {
        $resolvedItems = collect();
        $total = 0;

        foreach ($cartItems->sortBy(fn (array $item): int => (int) $item['menu']->id) as $cartItem) {
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

        return [$resolvedItems, $total];
    }

    private function reserveTransactionStock(Transaction $transaction): void
    {
        foreach ($transaction->items->sortBy(fn ($item): int => (int) $item->menu_id) as $item) {
            $quantity = (int) ($item->reserved_quantity ?? 0);
            if ($quantity > 0) {
                continue;
            }

            $menu = Menu::query()->lockForUpdate()->find($item->menu_id);
            if (! $menu || ! $menu->is_available || $menu->available_stock < $item->quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Stok {$item->menu_name} tidak cukup untuk melanjutkan pesanan.",
                ]);
            }

            $menu->increment('reserved_stock', $item->quantity);
            $item->update(['reserved_quantity' => $item->quantity]);
            $item->reserved_quantity = $item->quantity;
        }

        $transaction->update(['reservation_active' => true]);
    }

    private function releaseReservedStock(Transaction $transaction): void
    {
        foreach ($transaction->items->sortBy(fn ($item): int => (int) $item->menu_id) as $item) {
            $quantity = (int) ($item->reserved_quantity ?? 0);
            if ($quantity === 0) {
                continue;
            }

            $menu = Menu::query()->lockForUpdate()->find($item->menu_id);
            if (! $menu || $menu->reserved_stock < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Reservasi stok {$item->menu_name} tidak konsisten.",
                ]);
            }

            $menu->decrement('reserved_stock', $quantity);
            $item->update(['reserved_quantity' => 0]);
            $item->reserved_quantity = 0;
        }

        $transaction->update(['reservation_active' => false]);
    }

    private function commitReservedStock(Transaction $transaction): void
    {
        foreach ($transaction->items->sortBy(fn ($item): int => (int) $item->menu_id) as $item) {
            $quantity = (int) ($item->reserved_quantity ?? 0);
            if ($quantity === 0) {
                continue;
            }

            $menu = Menu::query()->lockForUpdate()->find($item->menu_id);
            if (! $menu || $menu->stock < $quantity || $menu->reserved_stock < $quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Reservasi stok {$item->menu_name} tidak konsisten.",
                ]);
            }

            $menu->decrement('stock', $quantity);
            $menu->decrement('reserved_stock', $quantity);
            $item->update(['reserved_quantity' => 0]);
            $item->reserved_quantity = 0;
        }

        $transaction->update(['reservation_active' => false]);
    }

    private function paymentChannel(array $data): ?string
    {
        return match ($data['payment_method']) {
            'qris' => 'other_qris',
            'bank_transfer' => $data['payment_channel'] ?? null,
            default => null,
        };
    }

    /** @return array{midtrans_transaction_id: null, midtrans_payment_type: null, midtrans_bank: null, midtrans_va_number: null, midtrans_snap_token: null} */
    private function clearGatewayAttributes(): array
    {
        return [
            'midtrans_transaction_id' => null,
            'midtrans_payment_type' => null,
            'midtrans_bank' => null,
            'midtrans_va_number' => null,
            'midtrans_snap_token' => null,
        ];
    }

    /** @return array{admin_id: int|null, kasir_id: int|null} */
    private function staffAttributes(Admin|Kasir $user): array
    {
        return $user instanceof Admin
            ? ['admin_id' => $user->id, 'kasir_id' => null]
            : ['admin_id' => null, 'kasir_id' => $user->id];
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'S1-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Transaction::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function newGatewayOrderId(): string
    {
        do {
            $number = 'S1G-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
        } while (Transaction::query()
            ->where('midtrans_order_id', $number)
            ->orWhereJsonContains('midtrans_order_id_history', $number)
            ->exists());

        return $number;
    }
}

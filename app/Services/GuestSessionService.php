<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Request;
use LogicException;

class GuestSessionService
{
    private const GUEST_ID_KEY = 'guest_session_id';

    private const PAYMENT_CHANGE_KEY = 'guest_payment_change_order';

    private const ACTIVE_ORDER_KEY = 'guest_active_order';

    public function __construct(private readonly Request $request) {}

    public function id(): string
    {
        $id = $this->request->session()->get(self::GUEST_ID_KEY);

        if (is_string($id) && $id !== '') {
            return $id;
        }

        $id = $this->request->session()->getId();

        if ($id === '') {
            throw new LogicException('Sesi pelanggan belum tersedia.');
        }

        $this->request->session()->put(self::GUEST_ID_KEY, $id);

        return $id;
    }

    public function sync(string $name): Customer
    {
        return Customer::query()->updateOrCreate(
            ['session_id' => $this->id()],
            ['name' => $name],
        );
    }

    public function rotate(): void
    {
        $this->request->session()->forget([
            self::GUEST_ID_KEY,
            self::PAYMENT_CHANGE_KEY,
            self::ACTIVE_ORDER_KEY,
        ]);
        $this->request->session()->regenerate();
    }

    public function preserveForPaymentChange(string $orderNumber): void
    {
        $this->request->session()->put(self::PAYMENT_CHANGE_KEY, $orderNumber);
    }

    public function isPaymentChange(string $orderNumber): bool
    {
        return $this->request->session()->get(self::PAYMENT_CHANGE_KEY) === $orderNumber;
    }

    public function paymentChangeOrder(): ?string
    {
        $orderNumber = $this->request->session()->get(self::PAYMENT_CHANGE_KEY);

        return is_string($orderNumber) && $orderNumber !== '' ? $orderNumber : null;
    }

    public function markActiveOrder(string $orderNumber): void
    {
        $this->request->session()->put(self::ACTIVE_ORDER_KEY, $orderNumber);
    }

    public function activeOrder(): ?string
    {
        $orderNumber = $this->request->session()->get(self::ACTIVE_ORDER_KEY);

        return is_string($orderNumber) && $orderNumber !== '' ? $orderNumber : null;
    }

    public function clearActiveOrder(): void
    {
        $this->request->session()->forget(self::ACTIVE_ORDER_KEY);
    }

    public function completePaymentChange(): void
    {
        $this->request->session()->forget(self::PAYMENT_CHANGE_KEY);
    }
}

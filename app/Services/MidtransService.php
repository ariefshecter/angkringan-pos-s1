<?php

namespace App\Services;

use App\Models\Transaction;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use RuntimeException;
use Throwable;

class MidtransService
{
    public function createSnapToken(Transaction $transaction): string
    {
        $this->configure();

        if (! config('services.midtrans.server_key') || ! config('services.midtrans.client_key')) {
            throw new RuntimeException('Konfigurasi Midtrans Sandbox belum diisi.');
        }

        $transaction->loadMissing('items');
        $params = [
            'transaction_details' => [
                'order_id' => $transaction->order_number,
                'gross_amount' => $transaction->total,
            ],
            'enabled_payments' => $this->enabledPaymentsFor($transaction),
            'item_details' => $transaction->items->map(fn ($item): array => [
                'id' => (string) ($item->menu_id ?? $item->id),
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => mb_substr($item->menu_name, 0, 50),
            ])->all(),
            'customer_details' => [
                'first_name' => $transaction->customer_name,
            ],
            'expiry' => [
                'unit' => 'minutes',
                'duration' => 15,
            ],
        ];

        return Snap::getSnapToken($params);
    }

    public function enabledPaymentsFor(Transaction $transaction): array
    {
        if ($transaction->payment_channel === 'other_qris') {
            return ['other_qris'];
        }

        $selectableChannels = config('services.midtrans.bank_channels', []);

        if (array_key_exists((string) $transaction->payment_channel, $selectableChannels)) {
            $bank = config('services.midtrans.core_bank_codes.'.$transaction->payment_channel);

            return [$bank.'_va'];
        }

        return config('services.midtrans.enabled_payments', []);
    }

    /** @return array{transaction_id: string, payment_type: string, bank: string, va_number: string} */
    public function createVirtualAccount(Transaction $transaction): array
    {
        $this->configure();

        if (! config('services.midtrans.server_key')) {
            throw new RuntimeException('Konfigurasi Server Key Midtrans Sandbox belum diisi.');
        }

        $bank = config('services.midtrans.core_bank_codes.'.$transaction->payment_channel);
        if (! $bank) {
            throw new RuntimeException('Bank Virtual Account yang dipilih tidak didukung.');
        }

        $transaction->loadMissing('items');
        $response = CoreApi::charge([
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $transaction->order_number,
                'gross_amount' => $transaction->total,
            ],
            'bank_transfer' => [
                'bank' => $bank,
            ],
            'item_details' => $transaction->items->map(fn ($item): array => [
                'id' => (string) ($item->menu_id ?? $item->id),
                'price' => $item->price,
                'quantity' => $item->quantity,
                'name' => mb_substr($item->menu_name, 0, 50),
            ])->all(),
            'customer_details' => [
                'first_name' => $transaction->customer_name,
            ],
            'custom_expiry' => [
                'order_time' => now()->format('Y-m-d H:i:s O'),
                'expiry_duration' => 15,
                'unit' => 'minute',
            ],
        ]);

        $vaNumber = $response->va_numbers[0]->va_number
            ?? $response->permata_va_number
            ?? null;
        $resolvedBank = $response->va_numbers[0]->bank ?? $bank;

        if (! $vaNumber || ! isset($response->transaction_id)) {
            throw new RuntimeException('Midtrans tidak mengembalikan nomor Virtual Account.');
        }

        return [
            'transaction_id' => (string) $response->transaction_id,
            'payment_type' => (string) ($response->payment_type ?? 'bank_transfer'),
            'bank' => (string) $resolvedBank,
            'va_number' => (string) $vaNumber,
        ];
    }

    public function cancelTransaction(Transaction $transaction): void
    {
        $this->configure();

        if (! config('services.midtrans.server_key')) {
            throw new RuntimeException('Konfigurasi Server Key Midtrans belum diisi.');
        }

        try {
            $statusCode = MidtransTransaction::cancel($transaction->order_number);
        } catch (Throwable $exception) {
            // Token Snap dapat gagal sebelum transaksi terbentuk di Midtrans.
            if ((int) $exception->getCode() === 404) {
                return;
            }

            throw new RuntimeException(
                'Pembayaran Midtrans tidak dapat dibatalkan. Periksa status pembayaran terlebih dahulu.',
                previous: $exception,
            );
        }

        if ((string) $statusCode !== '200') {
            throw new RuntimeException('Midtrans tidak mengonfirmasi pembatalan pembayaran.');
        }
    }

    private function configure(): void
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = (bool) config('services.midtrans.is_sanitized');
        Config::$is3ds = (bool) config('services.midtrans.is_3ds');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PaymentNotification;
use App\Models\Transaction;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, OrderService $orders): JsonResponse
    {
        $payload = $request->all();
        $orderNumber = (string) ($payload['order_id'] ?? '');
        $transaction = Transaction::query()
            ->where(function ($query) use ($orderNumber): void {
                $query->where('midtrans_order_id', $orderNumber)
                    ->orWhereJsonContains('midtrans_order_id_history', $orderNumber)
                    ->orWhere(function ($query) use ($orderNumber): void {
                        $query->whereNull('midtrans_order_id')
                            ->where('order_number', $orderNumber);
                    });
            })
            ->first();
        $expectedSignature = hash('sha512',
            $orderNumber.
            ($payload['status_code'] ?? '').
            ($payload['gross_amount'] ?? '').
            config('services.midtrans.server_key')
        );
        $isValid = $transaction
            && $orderNumber !== ''
            && filled(config('services.midtrans.server_key'))
            && hash_equals($expectedSignature, (string) ($payload['signature_key'] ?? ''))
            && (int) round((float) ($payload['gross_amount'] ?? 0)) === $transaction->total;

        $notification = PaymentNotification::query()->create([
            'transaction_id' => $transaction?->id,
            'order_number' => mb_substr($orderNumber ?: 'unknown', 0, 50),
            'transaction_status' => $payload['transaction_status'] ?? null,
            'signature_key' => $payload['signature_key'] ?? null,
            'payload' => $payload,
            'is_valid' => $isValid,
        ]);

        if (! $isValid) {
            return response()->json(['message' => 'Invalid notification signature.'], 403);
        }

        try {
            $status = (string) ($payload['transaction_status'] ?? '');
            $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');

            if ($status === 'settlement' || ($status === 'capture' && $fraudStatus === 'accept')) {
                $orders->markPaid($transaction, $payload);
            } elseif (in_array($status, ['cancel', 'deny', 'expire'], true)) {
                $mapped = match ($status) {
                    'expire' => Transaction::PAYMENT_EXPIRED,
                    'deny' => Transaction::PAYMENT_FAILED,
                    default => Transaction::PAYMENT_CANCELLED,
                };
                $orders->cancelFromMidtrans($transaction, $mapped, $payload);
            }

            $notification->update(['processed_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Notification processing failed.'], 500);
        }

        return response()->json(['message' => 'OK']);
    }
}

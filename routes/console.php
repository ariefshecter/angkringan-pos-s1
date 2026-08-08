<?php

use App\Models\Transaction;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $orders = app(OrderService::class);

    Transaction::query()
        ->where('payment_status', Transaction::PAYMENT_PENDING)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->each(fn (Transaction $transaction) => $orders->cancel($transaction, Transaction::PAYMENT_EXPIRED));
})->name('expire-pending-orders')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => app(CartService::class)->purgeExpired())
    ->name('expire-cart-reservations')
    ->everyMinute()
    ->withoutOverlapping();

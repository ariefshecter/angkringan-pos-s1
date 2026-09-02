<?php

namespace App\Exceptions;

use App\Models\Transaction;
use RuntimeException;
use Throwable;

class MidtransCancellationPendingException extends RuntimeException
{
    public function __construct(
        public readonly Transaction $transaction,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'Pembayaran belum dapat dibatalkan di Midtrans. Pesanan tetap Pending dan stok tetap direservasi.',
            previous: $previous,
        );
    }
}

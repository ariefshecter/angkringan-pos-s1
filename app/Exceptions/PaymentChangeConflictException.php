<?php

namespace App\Exceptions;

use App\Models\Transaction;
use RuntimeException;
use Throwable;

class PaymentChangeConflictException extends RuntimeException
{
    public function __construct(
        public readonly Transaction $transaction,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'Status pembayaran berubah saat pesanan sedang diperbarui.',
            previous: $previous,
        );
    }
}

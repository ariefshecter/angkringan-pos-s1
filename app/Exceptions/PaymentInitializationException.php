<?php

namespace App\Exceptions;

use App\Models\Transaction;
use RuntimeException;
use Throwable;

class PaymentInitializationException extends RuntimeException
{
    public function __construct(
        public readonly Transaction $transaction,
        Throwable $previous,
    ) {
        parent::__construct('Pembayaran belum berhasil dibuat. Pesanan yang sama tetap dapat dicoba kembali.', previous: $previous);
    }
}

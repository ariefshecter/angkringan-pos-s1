<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_EXPIRED = 'expired';

    public const PAYMENT_CANCELLED = 'cancelled';

    public const ORDER_NEW = 'new';

    public const ORDER_PROCESSING = 'processing';

    public const ORDER_READY = 'ready';

    public const ORDER_COMPLETED = 'completed';

    public const ORDER_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'handled_by',
        'device_id',
        'customer_name',
        'subtotal',
        'total',
        'payment_method',
        'payment_channel',
        'payment_status',
        'order_status',
        'midtrans_transaction_id',
        'midtrans_payment_type',
        'midtrans_snap_token',
        'midtrans_bank',
        'midtrans_va_number',
        'paid_at',
        'expires_at',
        'cancelled_at',
        'confirmed_at',
        'printed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function getPaymentLabelAttribute(): string
    {
        if ($this->payment_method === 'cash') {
            return 'Tunai';
        }

        if ($this->payment_channel === 'other_qris') {
            return 'QRIS';
        }

        $bank = config('services.midtrans.bank_channels.'.$this->payment_channel);

        if ($this->payment_channel === 'other_bank_va') {
            $issuer = config('services.midtrans.bank_code_labels.'.$this->midtrans_bank, 'Permata');

            return 'Transfer Bank Lainnya ('.$issuer.' VA)';
        }

        return $bank ? 'Transfer '.$bank.' VA' : 'Pembayaran digital';
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function paymentNotifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }
}

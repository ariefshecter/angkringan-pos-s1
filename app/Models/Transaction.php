<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transaksi';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_REPLACING = 'replacing';

    public const PAYMENT_REPLACED = 'replaced';

    public const PAYMENT_CANCELLING = 'cancelling';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_EXPIRED = 'expired';

    public const PAYMENT_CANCELLED = 'cancelled';

    public const ORDER_NEW = 'new';

    public const ORDER_REPLACING = 'replacing';

    public const ORDER_REPLACED = 'replaced';

    public const ORDER_CANCELLING = 'cancelling';

    public const ORDER_PROCESSING = 'processing';

    public const ORDER_READY = 'ready';

    public const ORDER_COMPLETED = 'completed';

    public const ORDER_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'midtrans_order_id',
        'midtrans_order_id_history',
        'user_session_id',
        'admin_id',
        'kasir_id',
        'device_id',
        'customer_name',
        'subtotal',
        'total',
        'payment_method',
        'payment_channel',
        'payment_status',
        'order_status',
        'reservation_active',
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
            'reservation_active' => 'boolean',
            'midtrans_order_id_history' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Transaction $transaction): void {
            if ($transaction->admin_id !== null && $transaction->kasir_id !== null) {
                throw ValidationException::withMessages([
                    'staff' => 'Transaksi hanya boleh ditangani oleh satu Admin atau Kasir.',
                ]);
            }
        });
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

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PAID => 'Lunas',
            self::PAYMENT_PENDING => 'Pending',
            self::PAYMENT_REPLACING => 'Mengganti metode pembayaran',
            self::PAYMENT_REPLACED => 'Metode pembayaran diganti',
            self::PAYMENT_CANCELLING => 'Membatalkan pesanan',
            self::PAYMENT_CANCELLED => 'Dibatalkan',
            self::PAYMENT_FAILED => 'Gagal',
            self::PAYMENT_EXPIRED => 'Kedaluwarsa',
            default => ucfirst((string) $this->payment_status),
        };
    }

    public function getOrderStatusLabelAttribute(): string
    {
        return match ($this->order_status) {
            self::ORDER_NEW => 'Baru',
            self::ORDER_REPLACING => 'Mengganti metode pembayaran',
            self::ORDER_REPLACED => 'Metode pembayaran diganti',
            self::ORDER_CANCELLING => 'Membatalkan pesanan',
            self::ORDER_PROCESSING => 'Diproses',
            self::ORDER_READY => 'Siap',
            self::ORDER_COMPLETED => 'Selesai',
            self::ORDER_CANCELLED => 'Dibatalkan',
            default => ucfirst((string) $this->order_status),
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_session_id', 'session_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(Kasir::class);
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

    public function isTerminalForGuest(): bool
    {
        return filled($this->confirmed_at)
            || ($this->payment_status === self::PAYMENT_CANCELLED && filled($this->cancelled_at))
            || $this->payment_status === self::PAYMENT_REPLACED;
    }

    public function gatewayOrderId(): string
    {
        return $this->midtrans_order_id ?: $this->order_number;
    }

    public function isExplicitlyCancelled(): bool
    {
        return $this->payment_status === self::PAYMENT_CANCELLED && filled($this->cancelled_at);
    }

    public function canBeEdited(): bool
    {
        return ! $this->isExplicitlyCancelled()
            && ! filled($this->confirmed_at)
            && in_array($this->payment_status, [
                self::PAYMENT_PENDING,
                self::PAYMENT_REPLACING,
                self::PAYMENT_FAILED,
                self::PAYMENT_EXPIRED,
                self::PAYMENT_CANCELLED,
                self::PAYMENT_REPLACED,
            ], true);
    }

    public function isActiveForGuest(): bool
    {
        return ! $this->isExplicitlyCancelled()
            && ! filled($this->confirmed_at)
            && in_array($this->payment_status, [
                self::PAYMENT_PENDING,
                self::PAYMENT_REPLACING,
                self::PAYMENT_CANCELLING,
                self::PAYMENT_PAID,
                self::PAYMENT_FAILED,
                self::PAYMENT_EXPIRED,
                self::PAYMENT_CANCELLED,
            ], true);
    }

    public function scopeLatestPerOrder(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->whereIn($table.'.id', function ($subquery) use ($table): void {
            $subquery->from($table)
                ->selectRaw('MAX(id)')
                ->groupBy('order_number');
        });
    }

    public function scopeActiveForGuest(Builder $query): Builder
    {
        return $query
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->whereIn('payment_status', [
                self::PAYMENT_PENDING,
                self::PAYMENT_REPLACING,
                self::PAYMENT_CANCELLING,
                self::PAYMENT_PAID,
                self::PAYMENT_FAILED,
                self::PAYMENT_EXPIRED,
                self::PAYMENT_CANCELLED,
            ]);
    }
}

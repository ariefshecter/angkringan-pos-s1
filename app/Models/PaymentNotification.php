<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'order_number',
        'transaction_status',
        'signature_key',
        'payload',
        'is_valid',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

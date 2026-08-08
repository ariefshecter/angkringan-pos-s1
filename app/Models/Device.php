<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    public const TYPE_ADMIN = 'admin';

    public const TYPE_KIOSK = 'kiosk';

    protected $fillable = [
        'name',
        'type',
        'activation_code_hash',
        'token_hash',
        'is_active',
        'activated_at',
        'last_seen_at',
        'last_ip',
        'user_agent',
    ];

    protected $hidden = ['activation_code_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function cartReservations(): HasMany
    {
        return $this->hasMany(CartReservation::class);
    }
}

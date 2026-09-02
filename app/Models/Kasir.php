<?php

namespace App\Models;

use Database\Factories\KasirFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Kasir extends Authenticatable
{
    /** @use HasFactory<KasirFactory> */
    use HasFactory, Notifiable;

    protected $table = 'kasir';

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function handledTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'kasir_id');
    }
}

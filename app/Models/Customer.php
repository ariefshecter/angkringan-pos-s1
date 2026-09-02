<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'user';

    protected $primaryKey = 'session_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'name',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_session_id', 'session_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentToken extends Model
{
    protected $fillable = [
        'user_id',
        'gateway',
        'token',
        'masked_pan',
        'card_type',
        'is_default',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCapability extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_entitlement_id', 'feature_code',
        'scope_type', 'scope_id', 'value'
    ];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(UserEntitlement::class, 'user_entitlement_id');
    }
}

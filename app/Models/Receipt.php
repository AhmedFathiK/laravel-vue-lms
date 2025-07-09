<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_id',
        'receipt_number',
        'item_type',
        'item_id',
        'item_name',
        'amount',
        'currency',
        'source_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user who owns the receipt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment associated with this receipt.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the payment associated with this receipt.
     */
    public function subscription(): HasOneThrough
    {
        return $this->hasOneThrough(UserSubscription::class, Payment::class, 'id', 'payment_id', 'id', 'id');
    }

    /**
     * Get the course associated with this receipt.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'item_id');
    }

    /**
     * Get the subscription plan associated with this receipt.
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'item_id');
    }

    /**
     * Generate a receipt number.
     */
    public static function generateUniqueReceiptNumber(): string
    {
        do {
            $number = self::generateReceiptNumber();
        } while (self::where('receipt_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a receipt number.
     */
    public static function generateReceiptNumber(): string
    {
        $prefix = 'RCP-';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        return $prefix . $timestamp . '-' . $random;
    }
}

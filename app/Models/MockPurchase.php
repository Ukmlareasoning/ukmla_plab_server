<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockPurchase extends Model
{
    protected $table = 'mock_purchases';

    protected $fillable = [
        'user_id',
        'mock_id',
        'stripe_payment_intent_id',
        'amount_paid',
        'payment_currency',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mock(): BelongsTo
    {
        return $this->belongsTo(Mock::class);
    }
}

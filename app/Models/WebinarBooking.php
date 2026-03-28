<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebinarBooking extends Model
{
    protected $table = 'webinar_bookings';

    protected $fillable = [
        'webinar_id',
        'user_id',
        'status',
        'stripe_payment_intent_id',
        'amount_paid',
        'payment_currency',
    ];

    public function webinar(): BelongsTo
    {
        return $this->belongsTo(Webinar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

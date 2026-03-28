<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivePackageSubscription extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'active_package_subscriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'plan_name',
        'amount',
        'starts_at',
        'ends_at',
        'status',
        'reference',
        'auto_renew',
        'cancelled_at',
        'stripe_subscription_id',
        'stripe_price_id',
        'stripe_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
        'auto_renew' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}


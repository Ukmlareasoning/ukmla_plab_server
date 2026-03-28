<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'plan_name',
        'price_display',
        'period_label',
        'amount',
        'duration_days',
        'duration_months',
        'features',
        'who_for',
        'is_popular',
        'saving_percent',
        'sort_order',
        'is_active',
        'stripe_price_id',
    ];

    /**
     * Resolved Stripe recurring Price ID: DB column, else env map in config/services.php.
     */
    public function resolvedStripePriceId(): ?string
    {
        if (!empty($this->stripe_price_id)) {
            return $this->stripe_price_id;
        }
        $map = config('services.stripe.package_prices', []);

        return $map[$this->slug] ?? null;
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'amount' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

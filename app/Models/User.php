<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'new_email',
        'password',
        'otp',
        'is_email_verified',
        'profile_image',
        'login_method',
        'gender',
        'status',
        'last_activity_at',
        'is_online',
        'is_agreed',
        'user_status',
        'admin_module_access',
        'is_subscribed',
        'subscription_type',
        'subscription_start_date',
        'subscription_end_date',
        'active_subscription_id',
        'stripe_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'otp',
        'remember_token',
        'stripe_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_email_verified' => 'boolean',
            'password' => 'hashed',
            'last_activity_at' => 'datetime',
            'is_online' => 'boolean',
            'is_agreed' => 'boolean',
            'admin_module_access' => 'array',
            'is_subscribed' => 'boolean',
            'subscription_start_date' => 'date',
            'subscription_end_date' => 'date',
        ];
    }
}

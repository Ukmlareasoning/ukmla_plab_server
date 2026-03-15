<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Webinar extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'webinars';

    protected $fillable = [
        'event_title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'presence',
        'zoom_meeting_link',
        'address',
        'price',
        'max_attendees',
        'banner_image',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'price' => 'decimal:2',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(WebinarBooking::class);
    }
}


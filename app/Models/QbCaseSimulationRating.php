<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QbCaseSimulationRating extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qb_case_simulation_ratings';

    protected $fillable = [
        'qb_case_simulation_id',
        'user_id',
        'stars',
        'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function caseSimulation(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulation::class, 'qb_case_simulation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

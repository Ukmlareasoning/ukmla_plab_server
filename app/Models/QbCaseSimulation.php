<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QbCaseSimulation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qb_case_simulations';

    protected $fillable = [
        'icon_key',
        'title',
        'description',
        'status',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(QbCaseSimulationQuestion::class, 'qb_case_simulation_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(QbCaseSimulationRating::class, 'qb_case_simulation_id');
    }

    public function userAnswers(): HasMany
    {
        return $this->hasMany(QbCaseSimulationUserAnswer::class, 'qb_case_simulation_id');
    }
}

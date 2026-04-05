<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QbCaseSimulationUserAnswer extends Model
{
    protected $table = 'qb_case_simulation_user_answers';

    protected $fillable = [
        'user_id',
        'qb_case_simulation_id',
        'qb_case_simulation_question_id',
        'selected_option_letter',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function caseSimulation(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulation::class, 'qb_case_simulation_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulationQuestion::class, 'qb_case_simulation_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

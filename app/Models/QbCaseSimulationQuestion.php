<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QbCaseSimulationQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'qb_case_simulation_questions';

    protected $fillable = [
        'qb_case_simulation_id',
        'question_type',
        'question',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function caseSimulation(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulation::class, 'qb_case_simulation_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QbCaseSimulationQuestionOption::class, 'qb_case_simulation_question_id');
    }

    public function aiTutor(): HasOne
    {
        return $this->hasOne(QbCaseSimulationQuestionAiTutor::class, 'qb_case_simulation_question_id');
    }
}

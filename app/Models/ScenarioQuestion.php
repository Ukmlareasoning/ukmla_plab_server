<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScenarioQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenario_questions';

    protected $fillable = [
        'scenario_id',
        'scenario_exam_id',
        'question_type',
        'question',
        'correct_option',
        'answer_description',
        'status',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function scenarioExam(): BelongsTo
    {
        return $this->belongsTo(ScenarioExam::class, 'scenario_exam_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ScenarioQuestionOption::class, 'scenario_question_id')
            ->orderBy('option_letter');
    }

    public function aiTutor(): HasOne
    {
        return $this->hasOne(ScenarioQuestionAiTutor::class, 'scenario_question_id');
    }
}

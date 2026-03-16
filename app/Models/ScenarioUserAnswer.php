<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioUserAnswer extends Model
{
    use HasFactory;

    protected $table = 'scenario_question_user_answers';

    protected $fillable = [
        'user_id',
        'scenario_id',
        'scenario_exam_id',
        'scenario_question_id',
        'user_answer',
        'is_correct',
        'attempted_at',
    ];

    protected $casts = [
        'is_correct'   => 'boolean',
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function scenarioExam(): BelongsTo
    {
        return $this->belongsTo(ScenarioExam::class, 'scenario_exam_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ScenarioQuestion::class, 'scenario_question_id');
    }
}

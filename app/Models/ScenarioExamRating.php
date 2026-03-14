<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioExamRating extends Model
{
    use HasFactory;

    protected $table = 'scenario_exam_ratings';

    protected $fillable = [
        'scenario_exam_id',
        'user_id',
        'stars',
        'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function scenarioExam(): BelongsTo
    {
        return $this->belongsTo(ScenarioExam::class, 'scenario_exam_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

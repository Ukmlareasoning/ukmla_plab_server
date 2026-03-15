<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenarios';

    protected $fillable = [
        'exam_type_id',
        'difficulty_level_id',
        'icon_key',
        'title',
        'description',
        'duration_type',
        'duration',
        'per_day_exams',
        'total_exams',
        'exams_release_mode',
        'status',
    ];

    public function examType(): BelongsTo
    {
        // Historically this referenced ExamType; scenarios now use NotesType as their "exam type"
        return $this->belongsTo(NotesType::class, 'exam_type_id');
    }

    public function difficultyLevel(): BelongsTo
    {
        return $this->belongsTo(DifficultyLevel::class, 'difficulty_level_id');
    }

    public function topicFocuses(): BelongsToMany
    {
        return $this->belongsToMany(
            ScenarioTopicFocus::class,
            'scenario_topic_focus_pivot',
            'scenario_id',
            'topic_focus_id'
        );
    }

    public function exams(): HasMany
    {
        return $this->hasMany(ScenarioExam::class, 'scenario_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ScenarioQuestion::class, 'scenario_id');
    }
}

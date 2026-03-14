<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mock extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mocks';

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
        'is_paid',
        'price_eur',
        'status',
    ];

    protected $casts = [
        'is_paid'   => 'boolean',
        'price_eur' => 'float',
    ];

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function difficultyLevel(): BelongsTo
    {
        return $this->belongsTo(DifficultyLevel::class, 'difficulty_level_id');
    }

    public function topicFocuses(): BelongsToMany
    {
        return $this->belongsToMany(
            TopicFocus::class,
            'mocks_topic_focus_pivot',
            'mock_id',
            'topic_focus_id'
        );
    }

    public function exams(): HasMany
    {
        return $this->hasMany(MockExam::class, 'mock_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MockQuestion::class, 'mock_id');
    }
}

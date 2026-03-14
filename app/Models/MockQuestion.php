<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MockQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mocks_questions';

    protected $fillable = [
        'mock_id',
        'mock_exam_id',
        'question_type',
        'question',
        'correct_option',
        'answer_description',
        'status',
    ];

    public function mock(): BelongsTo
    {
        return $this->belongsTo(Mock::class, 'mock_id');
    }

    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class, 'mock_exam_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MockQuestionOption::class, 'mocks_question_id')->orderBy('option_letter');
    }

    public function aiTutor(): HasOne
    {
        return $this->hasOne(MockQuestionAiTutor::class, 'mocks_question_id');
    }
}

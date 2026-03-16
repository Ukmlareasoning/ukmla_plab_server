<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockUserAnswer extends Model
{
    use HasFactory;

    protected $table = 'mock_question_user_answers';

    protected $fillable = [
        'user_id',
        'mock_id',
        'mock_exam_id',
        'mock_question_id',
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

    public function mock(): BelongsTo
    {
        return $this->belongsTo(Mock::class, 'mock_id');
    }

    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class, 'mock_exam_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(MockQuestion::class, 'mock_question_id');
    }
}

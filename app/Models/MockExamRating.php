<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockExamRating extends Model
{
    use HasFactory;

    protected $table = 'mocks_exam_ratings';

    protected $fillable = [
        'mocks_exam_id',
        'user_id',
        'stars',
        'comment',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class, 'mocks_exam_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

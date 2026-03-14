<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockExam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mocks_exams';

    protected $fillable = [
        'mock_id',
        'exam_no',
        'status',
    ];

    public function mock(): BelongsTo
    {
        return $this->belongsTo(Mock::class, 'mock_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MockQuestion::class, 'mock_exam_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(MockExamRating::class, 'mocks_exam_id');
    }
}

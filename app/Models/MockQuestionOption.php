<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockQuestionOption extends Model
{
    use HasFactory;

    protected $table = 'mocks_question_options';

    protected $fillable = [
        'mocks_question_id',
        'option_letter',
        'option_text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(MockQuestion::class, 'mocks_question_id');
    }
}

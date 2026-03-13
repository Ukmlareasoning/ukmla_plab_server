<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'notes';

    protected $fillable = [
        'notes_type_id',
        'difficulty_level_id',
        'title',
        'description',
        'summary',
        'key_points',
        'exam_importance_level',
        'tags',
        'status',
    ];

    protected $casts = [
        'key_points' => 'array',
        'tags' => 'array',
    ];

    public function type()
    {
        return $this->belongsTo(NotesType::class, 'notes_type_id');
    }

    public function difficultyLevel()
    {
        return $this->belongsTo(DifficultyLevel::class, 'difficulty_level_id');
    }
}


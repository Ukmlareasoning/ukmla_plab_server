<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockQuestionAiTutor extends Model
{
    use HasFactory;

    protected $table = 'mocks_question_ai_tutor';

    protected $fillable = [
        'mocks_question_id',
        'validation',
        'key_clues_identified',
        'missing_or_misweighted_clues',
        'examiner_logic',
        'option_by_option_elimination',
        'examiner_trap_alert',
        'pattern_recognition_label',
        'socratic_follow_up_question',
        'investigation_interpretation',
        'management_ladder',
        'guideline_justification',
        'safety_netting_red_flags',
        'exam_summary_box',
        'one_screen_memory_map',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(MockQuestion::class, 'mocks_question_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QbCaseSimulationQuestionAiTutor extends Model
{
    use HasFactory;

    protected $table = 'qb_case_simulation_question_ai_tutor';

    protected $fillable = [
        'qb_case_simulation_question_id',
        'validation',
        'key_clues',
        'missing_clues',
        'examiner_logic',
        'option_elimination',
        'trap_alert',
        'pattern_label',
        'socratic_follow_up',
        'investigation_interpretation',
        'management_ladder',
        'guideline_justification',
        'safety_netting',
        'exam_summary',
        'one_screen_map',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulationQuestion::class, 'qb_case_simulation_question_id');
    }
}

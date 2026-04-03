<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QbCaseSimulationQuestionOption extends Model
{
    use HasFactory;

    protected $table = 'qb_case_simulation_question_options';

    protected $fillable = [
        'qb_case_simulation_question_id',
        'option_letter',
        'option_text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QbCaseSimulationQuestion::class, 'qb_case_simulation_question_id');
    }
}

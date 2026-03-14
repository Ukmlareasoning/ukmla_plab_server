<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScenarioExam extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scenario_exams';

    protected $fillable = [
        'scenario_id',
        'exam_no',
        'status',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class, 'scenario_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ScenarioQuestion::class, 'scenario_exam_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ScenarioExamRating::class, 'scenario_exam_id');
    }
}

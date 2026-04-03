<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_questions') && Schema::hasColumn('qb_case_simulation_questions', 'answer')) {
            Schema::table('qb_case_simulation_questions', function (Blueprint $table) {
                $table->dropColumn('answer');
            });
        }

        if (!Schema::hasTable('qb_case_simulation_question_ai_tutor')) {
            return;
        }

        $extraAiTutorColumns = [
            'validation',
            'key_clues',
            'missing_clues',
            'pattern_label',
            'socratic_follow_up',
            'investigation_interpretation',
            'management_ladder',
            'guideline_justification',
            'safety_netting',
            'one_screen_map',
        ];

        $toDrop = array_values(array_filter($extraAiTutorColumns, fn ($col) => Schema::hasColumn('qb_case_simulation_question_ai_tutor', $col)));

        if ($toDrop !== []) {
            Schema::table('qb_case_simulation_question_ai_tutor', function (Blueprint $table) use ($toDrop) {
                $table->dropColumn($toDrop);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('qb_case_simulation_questions') && !Schema::hasColumn('qb_case_simulation_questions', 'answer')) {
            Schema::table('qb_case_simulation_questions', function (Blueprint $table) {
                $table->text('answer')->nullable()->after('question');
            });
        }

        if (!Schema::hasTable('qb_case_simulation_question_ai_tutor')) {
            return;
        }

        Schema::table('qb_case_simulation_question_ai_tutor', function (Blueprint $table) {
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'validation')) {
                $table->text('validation')->nullable()->after('qb_case_simulation_question_id');
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'key_clues')) {
                $table->text('key_clues')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'missing_clues')) {
                $table->text('missing_clues')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'pattern_label')) {
                $table->text('pattern_label')->nullable()->after('trap_alert');
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'socratic_follow_up')) {
                $table->text('socratic_follow_up')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'investigation_interpretation')) {
                $table->text('investigation_interpretation')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'management_ladder')) {
                $table->text('management_ladder')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'guideline_justification')) {
                $table->text('guideline_justification')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'safety_netting')) {
                $table->text('safety_netting')->nullable();
            }
            if (!Schema::hasColumn('qb_case_simulation_question_ai_tutor', 'one_screen_map')) {
                $table->text('one_screen_map')->nullable()->after('exam_summary');
            }
        });
    }
};

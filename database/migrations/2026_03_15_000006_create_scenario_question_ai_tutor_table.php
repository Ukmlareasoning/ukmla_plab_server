<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_question_ai_tutor')) {
            return;
        }

        Schema::create('scenario_question_ai_tutor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_question_id')->unique();
            $table->text('validation')->nullable();
            $table->text('key_clues_identified')->nullable();
            $table->text('missing_or_misweighted_clues')->nullable();
            $table->text('examiner_logic')->nullable();
            $table->text('option_by_option_elimination')->nullable();
            $table->text('examiner_trap_alert')->nullable();
            $table->text('pattern_recognition_label')->nullable();
            $table->text('socratic_follow_up_question')->nullable();
            $table->text('investigation_interpretation')->nullable();
            $table->text('management_ladder')->nullable();
            $table->text('guideline_justification')->nullable();
            $table->text('safety_netting_red_flags')->nullable();
            $table->text('exam_summary_box')->nullable();
            $table->text('one_screen_memory_map')->nullable();
            $table->timestamps();

            $table->foreign('scenario_question_id')
                ->references('id')->on('scenario_questions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_question_ai_tutor');
    }
};

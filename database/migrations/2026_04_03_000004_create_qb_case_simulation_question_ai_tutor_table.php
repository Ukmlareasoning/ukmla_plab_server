<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_question_ai_tutor')) {
            return;
        }

        Schema::create('qb_case_simulation_question_ai_tutor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qb_case_simulation_question_id')->unique();
            $table->text('examiner_logic')->nullable();
            $table->text('option_elimination')->nullable();
            $table->text('trap_alert')->nullable();
            $table->text('exam_summary')->nullable();
            $table->timestamps();

            $table->foreign('qb_case_simulation_question_id', 'fk_qb_cs_q_ai_tutor_question')
                ->references('id')->on('qb_case_simulation_questions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulation_question_ai_tutor');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_question_user_answers')) {
            return;
        }

        Schema::create('scenario_question_user_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('scenario_id');
            $table->unsignedBigInteger('scenario_exam_id');
            $table->unsignedBigInteger('scenario_question_id');
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('scenario_id')
                ->references('id')->on('scenarios')->cascadeOnDelete();
            $table->foreign('scenario_exam_id')
                ->references('id')->on('scenario_exams')->cascadeOnDelete();
            $table->foreign('scenario_question_id')
                ->references('id')->on('scenario_questions')->cascadeOnDelete();

            // One answer per user per question per exam attempt
            $table->unique(
                ['user_id', 'scenario_exam_id', 'scenario_question_id'],
                'uq_user_exam_question'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_question_user_answers');
    }
};

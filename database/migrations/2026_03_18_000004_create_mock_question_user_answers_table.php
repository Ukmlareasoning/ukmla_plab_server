<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mock_question_user_answers')) {
            return;
        }

        Schema::create('mock_question_user_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('mock_id');
            $table->unsignedBigInteger('mock_exam_id');
            $table->unsignedBigInteger('mock_question_id');
            $table->text('user_answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('mock_id')->references('id')->on('mocks')->cascadeOnDelete();
            $table->foreign('mock_exam_id')->references('id')->on('mocks_exams')->cascadeOnDelete();
            $table->foreign('mock_question_id')->references('id')->on('mocks_questions')->cascadeOnDelete();

            $table->unique(
                ['user_id', 'mock_exam_id', 'mock_question_id'],
                'uq_user_mock_exam_question'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_question_user_answers');
    }
};

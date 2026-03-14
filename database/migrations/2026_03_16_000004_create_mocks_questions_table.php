<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mocks_questions')) {
            return;
        }

        Schema::create('mocks_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_id');
            $table->unsignedBigInteger('mock_exam_id');
            $table->enum('question_type', ['mcq', 'shortAnswer', 'descriptive', 'trueFalse', 'fillInBlanks'])->default('mcq');
            $table->text('question');
            $table->string('correct_option', 1)->nullable();
            $table->text('answer_description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('mock_id')->references('id')->on('mocks')->cascadeOnDelete();
            $table->foreign('mock_exam_id')->references('id')->on('mocks_exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mocks_questions');
    }
};

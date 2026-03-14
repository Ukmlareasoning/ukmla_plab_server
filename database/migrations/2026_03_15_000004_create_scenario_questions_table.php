<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_questions')) {
            return;
        }

        Schema::create('scenario_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->unsignedBigInteger('scenario_exam_id');
            $table->enum('question_type', ['mcq', 'shortAnswer', 'descriptive', 'trueFalse', 'fillInBlanks'])->default('mcq');
            $table->text('question');
            $table->string('correct_option', 1)->nullable();   // A–E for MCQ
            $table->text('answer_description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
            $table->foreign('scenario_exam_id')->references('id')->on('scenario_exams')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_questions');
    }
};

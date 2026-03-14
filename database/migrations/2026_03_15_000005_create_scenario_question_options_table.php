<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_question_options')) {
            return;
        }

        Schema::create('scenario_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_question_id');
            $table->string('option_letter', 1);   // A, B, C, D, E
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('scenario_question_id')
                ->references('id')->on('scenario_questions')->cascadeOnDelete();

            $table->unique(['scenario_question_id', 'option_letter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_question_options');
    }
};

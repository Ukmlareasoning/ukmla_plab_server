<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mocks_question_options')) {
            return;
        }

        Schema::create('mocks_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mocks_question_id');
            $table->string('option_letter', 1);
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->foreign('mocks_question_id')
                ->references('id')->on('mocks_questions')->cascadeOnDelete();

            $table->unique(['mocks_question_id', 'option_letter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mocks_question_options');
    }
};

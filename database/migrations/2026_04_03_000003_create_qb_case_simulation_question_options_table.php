<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_question_options')) {
            return;
        }

        Schema::create('qb_case_simulation_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qb_case_simulation_question_id');
            $table->char('option_letter', 1);
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            // MySQL max identifier length 64; default Laravel name is too long for this table
            $table->foreign('qb_case_simulation_question_id', 'fk_qb_cs_q_opts_question')
                ->references('id')->on('qb_case_simulation_questions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulation_question_options');
    }
};

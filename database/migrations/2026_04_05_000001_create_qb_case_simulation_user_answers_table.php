<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_user_answers')) {
            return;
        }

        Schema::create('qb_case_simulation_user_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('qb_case_simulation_id');
            $table->unsignedBigInteger('qb_case_simulation_question_id');
            $table->string('selected_option_letter', 2);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(
                ['user_id', 'qb_case_simulation_question_id'],
                'uniq_qb_cs_user_answer_user_question',
            );

            $table->foreign('user_id', 'fk_qb_cs_ua_user')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('qb_case_simulation_id', 'fk_qb_cs_ua_case')
                ->references('id')->on('qb_case_simulations')
                ->onDelete('cascade');

            $table->foreign('qb_case_simulation_question_id', 'fk_qb_cs_ua_question')
                ->references('id')->on('qb_case_simulation_questions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulation_user_answers');
    }
};

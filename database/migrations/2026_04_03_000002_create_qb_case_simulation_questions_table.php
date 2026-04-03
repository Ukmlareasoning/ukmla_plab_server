<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_questions')) {
            return;
        }

        Schema::create('qb_case_simulation_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qb_case_simulation_id');
            $table->enum('question_type', ['mcq'])->default('mcq');
            $table->text('question');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('qb_case_simulation_id')
                ->references('id')->on('qb_case_simulations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulation_questions');
    }
};

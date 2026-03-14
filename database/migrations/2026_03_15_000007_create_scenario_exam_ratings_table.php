<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_exam_ratings')) {
            return;
        }

        Schema::create('scenario_exam_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_exam_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('stars')->default(5);    // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('scenario_exam_id')
                ->references('id')->on('scenario_exams')->cascadeOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['scenario_exam_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_exam_ratings');
    }
};

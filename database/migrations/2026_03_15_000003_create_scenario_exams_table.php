<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_exams')) {
            return;
        }

        Schema::create('scenario_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->unsignedSmallInteger('exam_no');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
            $table->unique(['scenario_id', 'exam_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_exams');
    }
};

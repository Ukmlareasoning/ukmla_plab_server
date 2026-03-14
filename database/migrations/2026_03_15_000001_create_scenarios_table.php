<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenarios')) {
            return;
        }

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_type_id')->nullable();
            $table->unsignedBigInteger('difficulty_level_id')->nullable();
            $table->string('icon_key', 64)->nullable();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->enum('duration_type', ['Week', 'Month'])->default('Week');
            $table->unsignedTinyInteger('duration')->default(1);
            $table->unsignedTinyInteger('per_day_exams')->default(1);
            $table->unsignedSmallInteger('total_exams')->default(0);
            $table->enum('exams_release_mode', ['all_at_once', 'one_after_another'])->default('all_at_once');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('exam_type_id')->references('id')->on('exam_types')->nullOnDelete();
            $table->foreign('difficulty_level_id')->references('id')->on('difficulty_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};

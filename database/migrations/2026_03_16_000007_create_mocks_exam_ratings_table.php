<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mocks_exam_ratings')) {
            return;
        }

        Schema::create('mocks_exam_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mocks_exam_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('stars')->default(5);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('mocks_exam_id')
                ->references('id')->on('mocks_exams')->cascadeOnDelete();
            $table->foreign('user_id')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['mocks_exam_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mocks_exam_ratings');
    }
};

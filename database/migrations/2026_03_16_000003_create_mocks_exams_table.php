<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mocks_exams')) {
            return;
        }

        Schema::create('mocks_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_id');
            $table->unsignedSmallInteger('exam_no');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('mock_id')->references('id')->on('mocks')->cascadeOnDelete();
            $table->unique(['mock_id', 'exam_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mocks_exams');
    }
};

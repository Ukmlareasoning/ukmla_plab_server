<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mocks_topic_focus_pivot')) {
            return;
        }

        Schema::create('mocks_topic_focus_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mock_id');
            $table->unsignedBigInteger('topic_focus_id');
            $table->timestamps();

            $table->foreign('mock_id')->references('id')->on('mocks')->cascadeOnDelete();
            $table->foreign('topic_focus_id')->references('id')->on('scenarios_topic_focus')->cascadeOnDelete();

            $table->unique(['mock_id', 'topic_focus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mocks_topic_focus_pivot');
    }
};

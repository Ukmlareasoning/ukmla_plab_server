<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scenario_topic_focus_pivot')) {
            return;
        }

        Schema::create('scenario_topic_focus_pivot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->unsignedBigInteger('topic_focus_id');
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
            $table->foreign('topic_focus_id')->references('id')->on('scenarios_topic_focus')->cascadeOnDelete();

            $table->unique(['scenario_id', 'topic_focus_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_topic_focus_pivot');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mocks_topic_focus_pivot')) {
            return;
        }

        Schema::table('mocks_topic_focus_pivot', function (Blueprint $table) {
            // Drop the old foreign key that pointed to scenarios_topic_focus
            $table->dropForeign(['topic_focus_id']);

            // Add new foreign key pointing to topic_focuses (courses topic focus)
            $table->foreign('topic_focus_id')->references('id')->on('topic_focuses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mocks_topic_focus_pivot')) {
            return;
        }

        Schema::table('mocks_topic_focus_pivot', function (Blueprint $table) {
            $table->dropForeign(['topic_focus_id']);
            $table->foreign('topic_focus_id')->references('id')->on('scenarios_topic_focus')->cascadeOnDelete();
        });
    }
};

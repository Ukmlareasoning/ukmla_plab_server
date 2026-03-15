<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow multiple static pages with the same page type (title/description).
     */
    public function up(): void
    {
        if (!Schema::hasTable('static_pages')) {
            return;
        }

        Schema::table('static_pages', function (Blueprint $table) {
            $table->dropUnique(['page']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('static_pages')) {
            return;
        }

        Schema::table('static_pages', function (Blueprint $table) {
            $table->unique('page');
        });
    }
};

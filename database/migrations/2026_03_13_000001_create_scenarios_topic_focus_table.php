<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guard against truncating or recreating existing tables
        if (Schema::hasTable('scenarios_topic_focus')) {
            return;
        }

        Schema::create('scenarios_topic_focus', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenarios_topic_focus');
    }
};


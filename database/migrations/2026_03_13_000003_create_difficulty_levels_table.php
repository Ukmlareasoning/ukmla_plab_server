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
        // Protect existing data: do nothing if table already exists
        if (Schema::hasTable('difficulty_levels')) {
            return;
        }

        Schema::create('difficulty_levels', function (Blueprint $table) {
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
        Schema::dropIfExists('difficulty_levels');
    }
};


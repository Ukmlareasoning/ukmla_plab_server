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
        // Do not modify if table already exists (protect existing data)
        if (Schema::hasTable('notes_types')) {
            return;
        }

        Schema::create('notes_types', function (Blueprint $table) {
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
        Schema::dropIfExists('notes_types');
    }
};


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
        // Do nothing if table already exists to avoid touching existing data
        if (Schema::hasTable('notes')) {
            return;
        }

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notes_type_id');
            $table->unsignedBigInteger('difficulty_level_id');
            $table->string('title', 191);
            $table->text('description');
            $table->text('summary')->nullable();
            $table->json('key_points')->nullable();
            $table->enum('exam_importance_level', ['Low', 'Medium', 'High']);
            $table->json('tags')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('notes_type_id')
                ->references('id')
                ->on('notes_types')
                ->onDelete('restrict');

            $table->foreign('difficulty_level_id')
                ->references('id')
                ->on('difficulty_levels')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};


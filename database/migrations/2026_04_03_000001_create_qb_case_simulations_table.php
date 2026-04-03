<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulations')) {
            return;
        }

        Schema::create('qb_case_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('icon_key', 64)->default('quiz');
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulations');
    }
};

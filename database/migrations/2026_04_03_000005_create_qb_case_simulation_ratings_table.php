<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qb_case_simulation_ratings')) {
            return;
        }

        Schema::create('qb_case_simulation_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qb_case_simulation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['qb_case_simulation_id', 'user_id'], 'uniq_qb_cs_rating_sim_user');

            $table->foreign('qb_case_simulation_id', 'fk_qb_cs_rating_sim')
                ->references('id')->on('qb_case_simulations')
                ->onDelete('cascade');

            $table->foreign('user_id', 'fk_qb_cs_rating_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qb_case_simulation_ratings');
    }
};

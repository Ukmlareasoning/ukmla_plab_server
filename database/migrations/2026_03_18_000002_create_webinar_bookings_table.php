<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('webinar_bookings')) {
            return;
        }

        Schema::create('webinar_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webinar_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('status', ['Confirmed', 'Cancelled'])->default('Confirmed');
            $table->timestamps();

            $table->foreign('webinar_id')->references('id')->on('webinars')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['webinar_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webinar_bookings');
    }
};

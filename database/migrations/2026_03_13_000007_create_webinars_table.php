<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('webinars')) {
            return;
        }

        Schema::create('webinars', function (Blueprint $table) {
            $table->id();
            $table->string('event_title', 191);
            $table->text('description');
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('presence', ['Online', 'Onsite']);
            $table->string('zoom_meeting_link', 512)->nullable();
            $table->text('address')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('max_attendees')->nullable();
            $table->string('banner_image', 255)->nullable();
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
        Schema::dropIfExists('webinars');
    }
};


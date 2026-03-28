<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webinar_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('webinar_bookings', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id', 255)->nullable()->unique()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webinar_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('webinar_bookings', 'stripe_payment_intent_id')) {
                $table->dropUnique(['stripe_payment_intent_id']);
                $table->dropColumn('stripe_payment_intent_id');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mock_purchases')) {
            return;
        }

        Schema::create('mock_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_id')->constrained('mocks')->cascadeOnDelete();
            $table->string('stripe_payment_intent_id', 255)->unique();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->string('payment_currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_purchases');
    }
};

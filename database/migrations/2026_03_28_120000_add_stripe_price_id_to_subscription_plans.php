<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_plans') && !Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('stripe_price_id', 191)->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_plans') && Schema::hasColumn('subscription_plans', 'stripe_price_id')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('stripe_price_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog plans for pricing UI + non-Stripe subscribe flow; Stripe columns for future use.
     */
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('title', 191);
                $table->string('plan_name', 191)->comment('Stored on active_package_subscriptions.plan_name');
                $table->string('price_display', 32)->nullable();
                $table->string('period_label', 64)->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->unsignedSmallInteger('duration_days')->nullable();
                $table->unsignedTinyInteger('duration_months')->nullable();
                $table->json('features')->nullable();
                $table->string('who_for', 255)->nullable();
                $table->boolean('is_popular')->default(false);
                $table->unsignedTinyInteger('saving_percent')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('active_package_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('active_package_subscriptions', 'auto_renew')) {
                $table->boolean('auto_renew')->default(true)->after('status');
            }
            if (!Schema::hasColumn('active_package_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('auto_renew');
            }
            if (!Schema::hasColumn('active_package_subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id', 191)->nullable()->after('reference');
            }
            if (!Schema::hasColumn('active_package_subscriptions', 'stripe_price_id')) {
                $table->string('stripe_price_id', 191)->nullable()->after('stripe_subscription_id');
            }
            if (!Schema::hasColumn('active_package_subscriptions', 'stripe_status')) {
                $table->string('stripe_status', 64)->nullable()->after('stripe_price_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stripe_id')) {
                $table->string('stripe_id', 191)->nullable()->after('active_subscription_id');
            }
        });

        if (Schema::hasTable('subscription_plans') && DB::table('subscription_plans')->count() === 0) {
            $now = now();
            DB::table('subscription_plans')->insert([
                [
                    'slug' => 'free_trial',
                    'title' => 'Free Trial',
                    'plan_name' => 'Free Trial',
                    'price_display' => '$0',
                    'period_label' => '7 days',
                    'amount' => 0,
                    'duration_days' => 7,
                    'duration_months' => null,
                    'features' => json_encode([
                        '20 reasoning sessions',
                        'Basic ethics scenarios',
                        'Progress tracking',
                        'Community support',
                    ]),
                    'who_for' => 'For exploring the platform',
                    'is_popular' => false,
                    'saving_percent' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'standard_monthly',
                    'title' => 'Standard',
                    'plan_name' => 'Standard',
                    'price_display' => '$5',
                    'period_label' => 'per month',
                    'amount' => 5,
                    'duration_days' => null,
                    'duration_months' => 1,
                    'features' => json_encode([
                        'Unlimited reasoning sessions',
                        'Full ethics & GMC access',
                        'Adaptive learning system',
                        'Weakness analysis',
                        'Progress dashboard',
                        'Email support',
                    ]),
                    'who_for' => 'For serious exam preparation',
                    'is_popular' => true,
                    'saving_percent' => null,
                    'sort_order' => 2,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'premium_quarterly',
                    'title' => 'Premium',
                    'plan_name' => 'Premium',
                    'price_display' => '$10',
                    'period_label' => '3 months',
                    'amount' => 10,
                    'duration_days' => null,
                    'duration_months' => 3,
                    'features' => json_encode([
                        'Everything in Standard',
                        'Priority AI tutor access',
                        'Custom study plans',
                        'Advanced analytics',
                        'Mock exam simulations',
                        'Priority support',
                    ]),
                    'who_for' => 'For comprehensive mastery',
                    'is_popular' => false,
                    'saving_percent' => 33,
                    'sort_order' => 3,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_package_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('active_package_subscriptions', 'stripe_status')) {
                $table->dropColumn('stripe_status');
            }
            if (Schema::hasColumn('active_package_subscriptions', 'stripe_price_id')) {
                $table->dropColumn('stripe_price_id');
            }
            if (Schema::hasColumn('active_package_subscriptions', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }
            if (Schema::hasColumn('active_package_subscriptions', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('active_package_subscriptions', 'auto_renew')) {
                $table->dropColumn('auto_renew');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stripe_id')) {
                $table->dropColumn('stripe_id');
            }
        });

        Schema::dropIfExists('subscription_plans');
    }
};

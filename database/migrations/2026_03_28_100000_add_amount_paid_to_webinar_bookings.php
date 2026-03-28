<?php

use App\Models\Webinar;
use App\Models\WebinarBooking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('webinar_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('webinar_bookings', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->nullable()->after('stripe_payment_intent_id');
            }
            if (!Schema::hasColumn('webinar_bookings', 'payment_currency')) {
                $table->string('payment_currency', 3)->nullable()->after('amount_paid');
            }
        });

        $currency = strtolower((string) env('STRIPE_CURRENCY', 'eur'));

        WebinarBooking::query()
            ->whereNotNull('stripe_payment_intent_id')
            ->whereNull('amount_paid')
            ->each(function (WebinarBooking $b) use ($currency) {
                $webinar = Webinar::find($b->webinar_id);
                if ($webinar) {
                    $b->update([
                        'amount_paid' => $webinar->price,
                        'payment_currency' => $currency,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('webinar_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('webinar_bookings', 'payment_currency')) {
                $table->dropColumn('payment_currency');
            }
            if (Schema::hasColumn('webinar_bookings', 'amount_paid')) {
                $table->dropColumn('amount_paid');
            }
        });
    }
};

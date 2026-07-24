<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('provider_payment_id');
            $table->string('stripe_checkout_session_id')->nullable()->after('provider_reference');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_session_id');
            $table->string('provider_status')->nullable()->after('stripe_payment_intent_id');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            $table->json('provider_payload')->nullable()->after('metadata');

            $table->index('stripe_checkout_session_id');
            $table->index('stripe_payment_intent_id');
            $table->index('provider_reference');
            $table->index('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['stripe_checkout_session_id']);
            $table->dropIndex(['stripe_payment_intent_id']);
            $table->dropIndex(['provider_reference']);
            $table->dropIndex(['provider_payment_id']);

            $table->dropColumn([
                'provider_reference',
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
                'provider_status',
                'failed_at',
                'cancelled_at',
                'provider_payload',
            ]);
        });
    }
};

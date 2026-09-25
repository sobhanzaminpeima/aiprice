<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gateways retry webhooks; recording each event id makes handling idempotent.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gateway', 30);
            $table->string('type', 100);
            $table->timestamp('processed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('gateway_customer_id')->nullable()->index();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['gateway', 'gateway_payment_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('gateway_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('gateway_customer_id'));
        Schema::table('payments', fn (Blueprint $t) => $t->dropIndex(['gateway', 'gateway_payment_id']));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropIndex(['gateway_subscription_id']));
    }
};

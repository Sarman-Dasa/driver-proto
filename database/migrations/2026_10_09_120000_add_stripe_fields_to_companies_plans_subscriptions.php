<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('currency')->index();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable()->after('currency');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->after('plan_id')->unique();
            $table->string('stripe_price_id')->nullable()->after('stripe_subscription_id');
            $table->string('billing_interval', 20)->default('monthly')->after('stripe_price_id');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_at_period_end');
            $table->timestamp('last_stripe_event_created_at')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'stripe_price_id', 'billing_interval', 'cancelled_at', 'last_stripe_event_created_at']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_monthly_price_id', 'stripe_yearly_price_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};

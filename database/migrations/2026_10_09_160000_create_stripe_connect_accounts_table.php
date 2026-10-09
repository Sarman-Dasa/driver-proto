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
        Schema::create('stripe_connect_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id')->unique()->index();
            $table->string('account_type', 30)->default('express');
            $table->string('country', 2)->default('US');
            $table->string('currency', 3)->default('USD');
            $table->boolean('charges_enabled')->default(false)->index();
            $table->boolean('payouts_enabled')->default(false)->index();
            $table->boolean('details_submitted')->default(false);
            $table->string('status', 40)->default('not_connected')->index();
            $table->json('capabilities')->nullable();
            $table->json('requirements')->nullable();
            $table->string('dashboard_url')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_accounts');
    }
};

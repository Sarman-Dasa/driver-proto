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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Internal and Stripe references
            $table->string('payment_reference', 64)->unique();
            $table->string('stripe_checkout_session_id', 255)->nullable()->index();
            $table->string('stripe_payment_intent_id', 255)->nullable()->index();
            $table->string('stripe_account_id', 255)->index();

            // Monetary values in minor units (integer cents)
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('USD');

            // Lifecycle status
            $table->string('status', 30)->default('pending')->index();
            $table->string('payment_method_type', 50)->nullable();
            $table->text('receipt_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Compound performance indexes
            $table->index(['company_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

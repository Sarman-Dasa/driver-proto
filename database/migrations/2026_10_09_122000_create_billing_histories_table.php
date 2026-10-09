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
        Schema::create('billing_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('stripe_invoice_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_event_id')->nullable()->index();
            $table->string('type', 40)->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('status', 30)->index();
            $table->string('billing_reason')->nullable();
            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf_url')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('event_at')->index();
            $table->timestamps();

            $table->index(['company_id', 'event_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_histories');
    }
};

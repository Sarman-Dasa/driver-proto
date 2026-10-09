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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number', 50);
            $table->string('status', 30)->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3)->default('USD');

            // Monetary values stored in minor units (integer cents)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 10, 2)->default(0.00);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('balance_due')->default(0);

            // Immutable historical snapshot of customer details at time of invoicing
            $table->json('customer_snapshot')->nullable();

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Scoped constraints & indexes
            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'issue_date']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->string('unit', 50)->nullable();

            // Monetary snapshots stored in minor units (integer cents)
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};

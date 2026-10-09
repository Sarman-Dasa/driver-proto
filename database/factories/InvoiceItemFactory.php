<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 5);
        $unitPrice = fake()->numberBetween(1000, 50000); // in cents
        $taxRate = 10.00;
        $subtotal = (int) round($quantity * $unitPrice);
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'product_name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit' => 'piece',
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
            'sort_order' => 0,
        ];
    }
}

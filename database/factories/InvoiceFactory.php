<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-1 month', 'now');
        $dueDate = (clone $issueDate)->modify('+30 days');

        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 99999),
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => $issueDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'currency' => 'USD',
            'subtotal' => 10000,
            'discount_type' => DiscountType::None->value,
            'discount_value' => 0.00,
            'discount_amount' => 0,
            'tax_amount' => 1000,
            'total_amount' => 11000,
            'amount_paid' => 0,
            'balance_due' => 11000,
            'customer_snapshot' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'company_name' => fake()->company(),
                'billing_address' => fake()->streetAddress(),
                'billing_city' => fake()->city(),
                'billing_state' => fake()->state(),
                'billing_postal_code' => fake()->postcode(),
                'billing_country' => fake()->country(),
            ],
            'notes' => fake()->sentence(),
            'terms' => 'Payment due within 30 days.',
            'created_by' => null,
            'updated_by' => null,
            'sent_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate that the invoice is sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }
}

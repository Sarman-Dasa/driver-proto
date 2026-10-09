<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(),
            'customer_id' => Customer::factory(),
            'payment_reference' => 'PAY-'.strtoupper(Str::random(12)),
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(24),
            'stripe_payment_intent_id' => 'pi_test_'.Str::random(24),
            'stripe_account_id' => 'acct_'.Str::random(16),
            'amount' => 10000,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'payment_method_type' => 'card',
            'receipt_url' => null,
            'paid_at' => null,
            'failure_reason' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }

    /**
     * Succeeded payment state.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);
    }

    /**
     * Failed payment state.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'Your card was declined.',
        ]);
    }
}

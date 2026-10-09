<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceDeliveryStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDelivery>
 */
class InvoiceDeliveryFactory extends Factory
{
    protected $model = InvoiceDelivery::class;

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
            'recipient_email' => fake()->safeEmail(),
            'subject' => 'Invoice from BillFlow',
            'message' => 'Thank you for your business. Please find attached invoice.',
            'status' => InvoiceDeliveryStatus::Sent,
            'attempts' => 1,
            'sent_at' => now(),
            'failed_at' => null,
            'error_message' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the delivery is queued.
     */
    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceDeliveryStatus::Queued,
            'attempts' => 0,
            'sent_at' => null,
            'failed_at' => null,
        ]);
    }

    /**
     * Indicate that the delivery failed.
     */
    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceDeliveryStatus::Failed,
            'attempts' => 1,
            'sent_at' => null,
            'failed_at' => now(),
            'error_message' => 'SMTP Connection failed: Connection timed out.',
        ]);
    }
}

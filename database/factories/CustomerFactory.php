<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $billingAddress = fake()->streetAddress();
        $billingCity = fake()->city();
        $billingState = fake()->stateAbbr();
        $billingPostal = fake()->postcode();
        $billingCountry = 'US';

        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company_name' => fake()->company(),
            'tax_number' => 'TAX-'.fake()->numerify('#####'),
            'billing_address' => $billingAddress,
            'billing_city' => $billingCity,
            'billing_state' => $billingState,
            'billing_postal_code' => $billingPostal,
            'billing_country' => $billingCountry,
            'shipping_same_as_billing' => true,
            'shipping_address' => $billingAddress,
            'shipping_city' => $billingCity,
            'shipping_state' => $billingState,
            'shipping_postal_code' => $billingPostal,
            'shipping_country' => $billingCountry,
            'notes' => fake()->sentence(),
            'status' => CustomerStatus::Active,
        ];
    }

    /**
     * Indicate that the customer is archived.
     */
    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => CustomerStatus::Archived,
            'deleted_at' => now(),
        ]);
    }

    /**
     * Associate customer with a specific company.
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn () => [
            'company_id' => $company->id,
        ]);
    }
}

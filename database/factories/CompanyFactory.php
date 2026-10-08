<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Company>
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 9999),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'logo' => null,
            'website' => 'https://'.fake()->domainName(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'tax_number' => 'TAX-'.fake()->numerify('######'),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => CompanyStatus::Active,
            'owner_id' => null,
        ];
    }

    /**
     * Set the owner for the company.
     */
    public function forOwner(User $user): static
    {
        return $this->state(fn () => [
            'owner_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the company is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CompanyStatus::Inactive,
        ]);
    }
}

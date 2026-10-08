<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => fake()->randomElement([ProductType::Product->value, ProductType::Service->value]),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'price' => fake()->numberBetween(1000, 100000), // $10.00 to $1,000.00 in minor units
            'currency' => 'USD',
            'tax_rate' => fake()->randomElement([0.0, 5.0, 10.0, 18.0, 20.0]),
            'unit' => fake()->randomElement(['piece', 'hour', 'month', 'project', 'license']),
            'status' => ProductStatus::Active->value,
        ];
    }

    /**
     * Indicate that the item is a tangible product.
     */
    public function product(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Product->value,
            'unit' => 'piece',
        ]);
    }

    /**
     * Indicate that the item is a service.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ProductType::Service->value,
            'unit' => 'hour',
        ]);
    }

    /**
     * Indicate that the item is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Inactive->value,
        ]);
    }
}

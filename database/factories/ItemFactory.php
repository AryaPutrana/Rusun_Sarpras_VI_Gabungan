<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_code' => strtoupper($this->faker->unique()->bothify('BRG-###')),
            'name' => $this->faker->words(3, true),
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'box', 'kg', 'meter', 'liter']),
            'stock' => $this->faker->numberBetween(0, 1000),
            'min_stock' => $this->faker->numberBetween(5, 50),
            'unit_price' => $this->faker->numberBetween(1000, 100000),
            'photo' => null,
        ];
    }

    /**
     * Indicate that the item has low stock.
     *
     * @return static
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $this->faker->numberBetween(0, 5),
            'min_stock' => 10,
        ]);
    }

    /**
     * Indicate that the item is out of stock.
     *
     * @return static
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
            'min_stock' => 10,
        ]);
    }
}

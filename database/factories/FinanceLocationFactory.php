<?php

namespace Database\Factories;

use App\Models\FinanceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceLocation>
 */
class FinanceLocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FinanceLocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_lokasi' => fake()->unique()->words(3, true) . ' - ' . fake()->city(),
            'keterangan' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['aktif', 'nonaktif']),
        ];
    }

    /**
     * Indicate that the location is active.
     *
     * @return static
     */
    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'aktif',
        ]);
    }

    /**
     * Indicate that the location is inactive.
     *
     * @return static
     */
    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'nonaktif',
        ]);
    }
}

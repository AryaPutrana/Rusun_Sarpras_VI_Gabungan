<?php

namespace Database\Factories;

use App\Models\FinanceLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinanceReport>
 */
class FinanceReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'location_id' => FinanceLocation::factory(),
            'tanggal' => fake()->date(),
            'kondisi' => fake()->randomElement(['normal', 'debit_air_kurang', 'tekanan_pdam_kurang']),
            'keterangan' => fake()->optional()->sentence(),
            'foto' => null,
            'foto_disk' => 'public',
        ];
    }
}

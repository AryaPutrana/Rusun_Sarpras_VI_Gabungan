<?php

namespace Database\Factories;

use App\Models\MonitoringLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaterMonitoring>
 */
class WaterMonitoringFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sesi = fake()->randomElement(['pagi', 'siang', 'sore']);
        $waktuMap = [
            'pagi' => '08:00:00',
            'siang' => '12:00:00',
            'sore' => '16:00:00',
        ];

        return [
            'user_id' => User::factory(),
            'location_id' => MonitoringLocation::factory(),
            'tanggal' => fake()->dateTimeBetween('-30 days', 'now'),
            'sesi' => $sesi,
            'waktu' => $waktuMap[$sesi],
            'kondisi' => fake()->randomElement(['normal', 'debit_turun', 'tekanan_air_kecil']),
            'keterangan' => fake()->sentence(),
            'foto' => 'monitoring/' . fake()->uuid() . '.jpg',
            'foto_disk' => 'evidence',
        ];
    }
}

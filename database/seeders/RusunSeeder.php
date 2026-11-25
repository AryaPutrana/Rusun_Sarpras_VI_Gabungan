<?php

namespace Database\Seeders;

use App\Models\Rusun;
use Illuminate\Database\Seeder;

class RusunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Production guard - jangan seed data master di production
        if (app()->environment('production')) {
            $this->command->warn('⚠️  Seeder tidak dijalankan di production.');
            $this->command->info('Tambahkan data rusun secara manual di production.');
            return;
        }

        $rusunData = [
            ['code' => 'RABEK', 'name' => 'Rawa Bebek'],
            ['code' => 'UMEN', 'name' => 'Umen'],
            ['code' => 'TIPAR', 'name' => 'Tipar'],
            ['code' => 'ALBO', 'name' => 'Albo'],
            ['code' => 'CBT', 'name' => 'CBT'],
            ['code' => 'KM2', 'name' => 'KM2'],
        ];

        foreach ($rusunData as $rusun) {
            Rusun::firstOrCreate(
                ['code' => $rusun['code']], // Lookup by code
                ['name' => $rusun['name']]   // Set name if creating
            );
        }
    }
}

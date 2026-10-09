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

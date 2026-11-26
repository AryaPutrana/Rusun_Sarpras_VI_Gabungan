<?php

namespace Database\Seeders;

use App\Models\MonitoringLocation;
use Illuminate\Database\Seeder;

class MonitoringLocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Production guard - jangan seed data master di production
        if (app()->environment('production')) {
            $this->command->warn('⚠️  Seeder tidak dijalankan di production.');
            $this->command->info('Tambahkan data monitoring location secara manual di production.');
            return;
        }

        $locations = [
            ['nama_lokasi' => 'GWT', 'keterangan' => 'Ground Water Tank utama', 'status' => 'aktif'],
            ['nama_lokasi' => 'Kolam Air Bersih', 'keterangan' => 'Kolam air bersih utama', 'status' => 'aktif'],
        ];

        foreach ($locations as $location) {
            MonitoringLocation::firstOrCreate(
                ['nama_lokasi' => $location['nama_lokasi']],
                $location
            );
        }
    }
}

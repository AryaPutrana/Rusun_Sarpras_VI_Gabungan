<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * IMPORTANT: Gunakan password kuat di produksi!
     * Password default ini HANYA untuk development/testing.
     */
    public function run(): void
    {
        // Cek environment - jangan seed di production!
        if (app()->environment('production')) {
            $this->command->warn('⚠️  Seeder tidak dijalankan di environment production.');
            $this->command->warn('⚠️  Buat user admin manual dengan password kuat.');
            return;
        }

        $users = [
            [
                'name' => 'Admin Sarpras',
                'email' => 'admin@example.com',
                'password' => Hash::make(Str::random(16)), // Random password
                'role' => 'admin'
            ],
            [
                'name' => 'Petugas Sarpras',
                'email' => 'petugas@example.com',
                'password' => Hash::make(Str::random(16)), // Random password
                'role' => 'petugas'
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'role' => $userData['role'],
                ]
            );

            // Output password untuk development (JANGAN di production!)
            if (app()->environment('local', 'development')) {
                $this->command->info("User created: {$userData['email']}");
            }
        }

        $this->command->info('✓ Development users seeded successfully.');
        $this->command->warn('⚠️  JANGAN gunakan seeder ini di production!');
    }
}

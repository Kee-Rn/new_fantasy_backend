<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user (Filament CMS access) ──────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@karnaliyaks.com'],
            [
                'name'     => 'Admin',
                'email'    => 'admin@karnaliyaks.com',
                'password' => Hash::make('admin123'),
                'role'     => 'admin',
            ]
        );

        // ── Regular test user (API testing) ───────────────────────────────
        User::updateOrCreate(
            ['email' => 'test@karnaliyaks.com'],
            [
                'name'     => 'Test User',
                'email'    => 'test@karnaliyaks.com',
                'password' => Hash::make('password'),
                'role'     => 'user',
            ]
        );

        $this->command->info('✓ Admin user: admin@karnaliyaks.com / admin123');
        $this->command->info('✓ Test user:  test@karnaliyaks.com / password');
    }
}
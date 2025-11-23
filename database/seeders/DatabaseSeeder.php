<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Buat Manager
        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        // Buat Host 1
        $host1 = User::create([
            'name' => 'Host Satu',
            'email' => 'host1@example.com',
            'password' => Hash::make('password'),
            'role' => 'host',
        ]);

        // Buat Host 2
        $host2 = User::create([
            'name' => 'Host Dua',
            'email' => 'host2@example.com',
            'password' => Hash::make('password'),
            'role' => 'host',
        ]);

        // Buat Assets untuk Host 1
        Asset::create([
            'name' => 'Akun TikTok Shop A',
            'platform' => 'TikTok',
            'user_id' => $host1->id,
        ]);

        // Buat Assets untuk Host 2
        Asset::create([
            'name' => 'Akun Shopee B',
            'platform' => 'Shopee',
            'user_id' => $host2->id,
        ]);
    }
}
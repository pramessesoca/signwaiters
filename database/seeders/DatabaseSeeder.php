<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'username' => env('ADMIN_USERNAME', 'admin'),
        ], [
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => env('ADMIN_EMAIL', 'admin@local.test'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'admin12345')),
            'is_admin' => true,
        ]);
    }
}

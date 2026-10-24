<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one organization holding the local login-link users.
     */
    public function run(): void
    {
        $organization = Organization::factory()->create(['name' => 'Test Organization']);

        User::factory()->for($organization)->admin()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->for($organization)->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
        ]);
    }
}

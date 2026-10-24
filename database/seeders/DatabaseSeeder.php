<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one company holding the local login-link users.
     */
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);

        User::factory()->for($company)->admin()->create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->for($company)->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleSeeder::class);
        
        // Seed user accounts with different roles
        $this->call(UserSeeder::class);
        
        // Seed system default carriers for new users
        $this->call(SystemCarrierSeeder::class);
    }
}

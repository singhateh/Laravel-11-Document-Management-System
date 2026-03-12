<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin account
        User::factory()->create([
            'name' => 'System Administrator',
            'username' => 'admin',
            'email' => 'admin@stegolock.local',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
        
        // Owner account
        User::factory()->create([
            'name' => 'Company Owner',
            'username' => 'owner',
            'email' => 'owner@stegolock.local',
            'password' => Hash::make('owner123'),
            'role' => 'owner',
        ]);
        
        // Regular user accounts
        User::factory()->create([
            'name' => 'John Doe',
            'username' => 'john.doe',
            'email' => 'john.doe@stegolock.local',
            'password' => Hash::make('user123'),
            'role' => 'user',
        ]);
        
        User::factory()->create([
            'name' => 'Jane Smith',
            'username' => 'jane.smith',
            'email' => 'jane.smith@stegolock.local',
            'password' => Hash::make('user123'),
            'role' => 'user',
        ]);
        
        User::factory()->create([
            'name' => 'Mike Johnson',
            'username' => 'mike.johnson',
            'email' => 'mike.johnson@stegolock.local',
            'password' => Hash::make('user123'),
            'role' => 'user',
        ]);
        
        // Additional random users
        User::factory()->count(7)->create(['role' => 'user']);
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\UserRoleEnum;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'), // Hash the password
            'role' => UserRoleEnum::SUPER_ADMIN, // Assign Super Admin role
        ]);
        // Inventory Manager User
        User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => UserRoleEnum::INVENTORY_MANAGER, // Assign Inventory Manager role
        ]);
        // Regular Inventory User
        User::create([
            'name' => 'Basic User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => UserRoleEnum::INVENTORY_USER, // Assign Inventory User role
        ]);
    }
}

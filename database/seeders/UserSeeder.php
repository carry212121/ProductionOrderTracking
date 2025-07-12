<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'username' => 'adminuser',
            'password' => Hash::make('admin123'),
            'productionID' => 'P001',
            'role' => 'Admin',
        ]);

        User::create([
            'name' => 'Sales User',
            'username' => 'salesuser',
            'password' => Hash::make('sales123'),
            'productionID' => 'P003',
            'role' => 'Sales',
        ]);

        User::create([
            'name' => 'Head User',
            'username' => 'headuser',
            'password' => Hash::make('head123'),
            'productionID' => 'P004',
            'role' => 'Head',
        ]);
    }
}

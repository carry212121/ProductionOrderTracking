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
        // 🔰 Head Users
        User::create([
            'username' => 'Paisal',
            'name' => 'Paisal PS',
            'password' => Hash::make('head123'),
            'productionID' => 'PS',
            'salesID' => 'PS',
            'role' => 'Head',
        ]);

        User::create([
            'username' => 'Rosalynn',
            'name' => 'Rosalynn RS',
            'password' => Hash::make('head123'),
            'productionID' => 'RS',
            'salesID' => 'RS',
            'role' => 'Head',
        ]);

        // 🔰 Admin User
        User::create([
            'username' => 'Chutapa',
            'name' => 'Chutapa BN',
            'password' => Hash::make('admin123'),
            'productionID' => 'KT',
            'salesID' => 'KT',
            'role' => 'Admin',
        ]);

        // 📦 Sales Users
        $sales = [
            ['username' => 'Waree', 'name' => 'Waree WR', 'salesID' => 'WR'],
            ['username' => 'Onanong', 'name' => 'Onanong ON', 'salesID' => 'ON'],
            ['username' => 'Ancharat', 'name' => 'Ancharat PM', 'salesID' => 'PM'],
            ['username' => 'Pakpoom', 'name' => 'Pakpoom BN', 'salesID' => 'BN'],
        ];

        foreach ($sales as $s) {
            User::create([
                'username' => $s['username'],
                'name' => $s['name'],
                'password' => Hash::make('sales123'),
                'salesID' => $s['salesID'],
                'role' => 'Sales',
            ]);
        }
    }
}

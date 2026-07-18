<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Sleep Audio Solutions',
            'Everyday Audio Series',
            'Gaming Audio Series',
            'Innovation Series',
            'Charging Solutions',
        ] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }

        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@123'),
                'is_active' => true,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            ['username' => 'admin1', 'password' => 'admin123'],
            ['username' => 'admin2', 'password' => 'admin123'],
            ['username' => 'admin3', 'password' => 'admin123'],
            ['username' => 'admin4', 'password' => 'admin123'],
            ['username' => 'admin5', 'password' => 'admin123'],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['username' => $admin['username']],
                [
                    'password' => Hash::make($admin['password']),
                    'role'     => 'admin',
                ]
            );
        }
    }
}

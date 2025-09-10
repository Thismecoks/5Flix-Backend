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
            [
                'username' => env('ADMIN1_USERNAME', 'admin1'),
                'password' => env('ADMIN1_PASSWORD', 'admin123'),
            ],
            [
                'username' => env('ADMIN2_USERNAME', 'admin2'),
                'password' => env('ADMIN2_PASSWORD', 'admin123'),
            ],
            [
                'username' => env('ADMIN3_USERNAME', 'admin3'),
                'password' => env('ADMIN3_PASSWORD', 'admin123'),
            ],
            [
                'username' => env('ADMIN4_USERNAME', 'admin4'),
                'password' => env('ADMIN4_PASSWORD', 'admin123'),
            ],
            [
                'username' => env('ADMIN5_USERNAME', 'admin5'),
                'password' => env('ADMIN5_PASSWORD', 'admin123'),
            ],
        ];

        foreach ($admins as $admin) {
            if ($admin['username'] && $admin['password']) {
                User::updateOrCreate(
                    ['username' => $admin['username']], // cari username
                    [
                        'password' => Hash::make($admin['password']),
                        'role'     => 'admin',
                    ]
                );
            }
        }
    }
}

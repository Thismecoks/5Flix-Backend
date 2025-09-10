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
                'username' => env('ADMIN1_USERNAME'),
                'password' => env('ADMIN1_PASSWORD'),
            ],
            [
                'username' => env('ADMIN2_USERNAME'),
                'password' => env('ADMIN2_PASSWORD'),
            ],
            [
                'username' => env('ADMIN3_USERNAME'),
                'password' => env('ADMIN3_PASSWORD'),
            ],
            [
                'username' => env('ADMIN4_USERNAME'),
                'password' => env('ADMIN4_PASSWORD'),
            ],
            [
                'username' => env('ADMIN5_USERNAME'),
                'password' => env('ADMIN5_PASSWORD'),
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

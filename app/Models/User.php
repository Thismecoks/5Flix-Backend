<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'role'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // Override the username field for authentication
    public function username()
    {
        return 'username';
    }

    // Add this method to ensure proper authentication
    public function getAuthIdentifierName()
    {
        return 'username';
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * Get only valid (non-expired) refresh tokens for the user.
     */
    public function validRefreshTokens()
    {
        return $this->refreshTokens()->valid();
    }
}
<?php

namespace Admin9\OidcClient\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'oidc_sub',
        'auth_server_refresh_token',
    ];

    protected $hidden = [
        'password',
        'auth_server_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'auth_server_refresh_token' => 'encrypted',
        ];
    }
}

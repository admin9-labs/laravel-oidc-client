# Laravel OIDC Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/admin9-labs/laravel-oidc-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/admin9-labs/laravel-oidc-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)

English | [简体中文](docs/zh-CN/README.md)

A Laravel package for OIDC (OpenID Connect) authentication with PKCE support.

## Features

- ✅ OIDC Authorization Code Flow with PKCE
- ✅ Automatic user provisioning from OIDC claims
- ✅ Flexible user mapping configuration
- ✅ Token revocation support
- ✅ Rate limiting on all endpoints
- ✅ Event system for authentication lifecycle

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- JWT package: [`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth) or [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth)
- Persistent cache driver (redis, database, file)
- Persistent session driver (redis, database, file)

## Installation

```bash
composer require admin9/laravel-oidc-client
php artisan vendor:publish --tag="oidc-client-config"
php artisan vendor:publish --tag="oidc-client-migrations"
php artisan migrate
```

## Configuration

Add to `.env`:

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=http://localhost:8000/auth/callback
OIDC_FRONTEND_URL=http://localhost:3000
```

Update `app/Models/User.php`:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'oidc_sub',
    'auth_server_refresh_token',
];

protected $hidden = [
    'password',
    'remember_token',
    'auth_server_refresh_token',
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'auth_server_refresh_token' => 'encrypted',
    ];
}
```

## Usage

### Backend Routes

The package registers these routes:

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/auth/redirect` | Start OIDC flow |
| GET | `/auth/callback` | Handle callback |
| POST | `/api/auth/exchange` | Exchange code for JWT |

### Frontend Integration

#### 1. Login

Redirect to start OIDC flow:

```javascript
window.location.href = 'http://localhost:8000/auth/redirect';
```

#### 2. Callback

Handle callback in your frontend:

```javascript
const params = new URLSearchParams(window.location.search);
const code = params.get('code');

if (code) {
  const response = await fetch('http://localhost:8000/api/auth/exchange', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code }),
  });

  const data = await response.json();
  if (data.success) {
    localStorage.setItem('token', data.data.access_token);
    window.location.href = '/dashboard';
  }
}
```

#### 3. Using JWT

Use the token for authenticated requests:

```javascript
fetch('http://localhost:8000/api/user', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
  },
});
```

## Documentation

- [Configuration](docs/configuration.md) - All config options and environment variables
- [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions

## License

MIT

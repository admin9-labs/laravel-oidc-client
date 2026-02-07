# Admin9 Laravel OIDC Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/admin9-labs/laravel-oidc-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/admin9-labs/laravel-oidc-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)

A Laravel package for OIDC (OpenID Connect) authentication with PKCE support, designed for SSO/SLO (Single Sign-On/Single Logout) flows.

## Features

- OIDC Authorization Code Flow with PKCE (Proof Key for Code Exchange)
- State parameter validation for CSRF protection
- One-time exchange codes with configurable TTL
- Configurable user mapping (column names, userinfo→attribute mapping)
- OidcService for Auth Server token revocation (use in your logout)
- Configurable routes and middleware
- Rate limiting on sensitive endpoints
- Publishable translation files for error messages

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- JWT authentication package (e.g., [`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth) or [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth)) configured with an `api` guard

## Installation

```bash
composer require admin9/laravel-oidc-client
```

### Publish Configuration

```bash
php artisan vendor:publish --tag="oidc-client-config"
```

### Publish Migrations

```bash
php artisan vendor:publish --tag="oidc-client-migrations"
php artisan migrate
```

### Publish Translations (optional)

```bash
php artisan vendor:publish --tag="oidc-client-translations"
```

## Configuration

The config key is `oidc-client` (to avoid collision with the server package's `oidc` key when both are installed).

Configure your OIDC provider in `.env`:

```env
# Auth Server Configuration
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=http://localhost/auth/callback

# Frontend URL (for redirects after auth)
OIDC_FRONTEND_URL=http://localhost:3000

# Optional: Customize scopes
OIDC_SCOPES="openid profile email"

# Optional: Exchange code TTL (minutes)
OIDC_EXCHANGE_CODE_TTL=5

# Optional: JWT guard name
OIDC_JWT_GUARD=api
```

## User Model Setup

Your User model needs the following fields (column names are configurable via `user_mapping`):

```php
// Migration (default column names)
Schema::table('users', function (Blueprint $table) {
    $table->string('oidc_sub')->nullable()->unique();
    $table->text('auth_server_refresh_token')->nullable();
    $table->string('password')->nullable()->change(); // For OIDC-only users
});
```

Add the encrypted cast for the refresh token:

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'auth_server_refresh_token' => 'encrypted',
        // ...
    ];
}

protected $fillable = [
    'name', 'email', 'password', 'oidc_sub', 'auth_server_refresh_token',
];

protected $hidden = [
    'password', 'auth_server_refresh_token',
];
```

### User Mapping Configuration

Customize how OIDC userinfo claims map to your User model in `config/oidc-client.php`:

```php
'user_mapping' => [
    'identifier_column' => 'oidc_sub',           // DB column for OIDC subject ID
    'identifier_claim' => 'sub',                  // Userinfo claim used as identifier
    'refresh_token_column' => 'auth_server_refresh_token',
    'attributes' => [
        'name' => fn ($userinfo) => $userinfo['name'] ?? $userinfo['email'],
        'email' => fn ($userinfo) => $userinfo['email'],
    ],
],
```

## Routes

The package registers the following routes:

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/auth/redirect` | `oidc.redirect` | Initiates OIDC authorization |
| GET | `/auth/callback` | `oidc.callback` | Handles Auth Server callback |
| POST | `/api/auth/exchange` | `oidc.exchange` | Exchanges code for JWT token |

**Note**: Logout is NOT provided by the package. You should implement it in your AuthController using `OidcService`.

## OidcService

The package provides `OidcService` for use in your controllers:

```php
use Admin9\OidcClient\Services\OidcService;

class AuthController extends Controller
{
    public function logout(Request $request, OidcService $oidcService): JsonResponse
    {
        $user = $request->user();

        // Revoke Auth Server token if user logged in via OIDC
        // (Does nothing if user has no auth_server_refresh_token)
        $oidcService->revokeAuthServerToken($user);

        // Blacklist JWT
        auth('api')->logout();

        // Return SSO logout URL for OIDC users
        $data = ['message' => 'Successfully logged out'];
        if ($oidcService->isOidcUser($user)) {
            $data['logout_url'] = $oidcService->getSsoLogoutUrl();
        }

        return response()->json($data);
    }
}
```

### OidcService Methods

| Method | Description |
|--------|-------------|
| `revokeAuthServerToken($user)` | Revokes Auth Server token and clears stored refresh token |
| `getSsoLogoutUrl()` | Returns the SSO logout URL for frontend redirect |
| `isOidcUser($user)` | Checks if user authenticated via OIDC |
| `findOrCreateUser($userData, $refreshToken)` | Creates or updates a local user from OIDC userinfo |

## Events

The package dispatches events you can listen to in your application:

| Event | Payload | When |
|-------|---------|------|
| `OidcUserAuthenticated` | `$user`, `$userInfo`, `$isNewUser` | After successful OIDC callback and user creation |
| `OidcTokenExchanged` | `$user` | After exchange code is swapped for a JWT |
| `OidcAuthFailed` | `$errorCode`, `$errorMessage` | On authorization denial or server error |

```php
// Example: Send welcome email to new OIDC users
use Admin9\OidcClient\Events\OidcUserAuthenticated;

class SendWelcomeEmail
{
    public function handle(OidcUserAuthenticated $event): void
    {
        if ($event->isNewUser) {
            // Send welcome email to $event->user
        }
    }
}
```

## Authentication Flow

```
Frontend        API           Package       Auth Server
  │               │               │               │
  │ 1. Login      │               │               │
  │──────────────>│               │               │
  │               │ 2. /auth/redirect             │
  │               │──────────────>│               │
  │               │               │ 3. PKCE+State │
  │               │               │ 4. Redirect   │
  │<─────────────────────────────────────────────>│
  │               │               │               │
  │ 5. User authenticates with Auth Server        │
  │<─────────────────────────────────────────────>│
  │               │               │               │
  │               │               │ 6. /callback  │
  │               │               │<──────────────│
  │               │               │ 7-10. Validate│
  │               │               │   & exchange  │
  │ 12. Redirect with code        │               │
  │<──────────────────────────────│               │
  │               │               │               │
  │ 13. POST /api/auth/exchange   │               │
  │──────────────>│──────────────>│               │
  │               │               │14-15. JWT     │
  │<──────────────│<──────────────│               │
  │ 16. JWT Token │               │               │
```

## Security Features

1. **PKCE (Proof Key for Code Exchange)**: SHA-256 code challenge prevents authorization code interception
2. **State Parameter**: 40-character random string prevents CSRF attacks
3. **One-Time Exchange Codes**: UUID format, configurable expiration (default 5 minutes)
4. **Encrypted Refresh Tokens**: Auth Server refresh tokens are encrypted at rest
5. **Rate Limiting**: Exchange endpoint is rate-limited (default 10 requests/minute)
6. **OidcService**: Provides Auth Server token revocation for complete logout

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Qiyue Feng](https://github.com/fengqiyue)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

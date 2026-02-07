# Configuration Reference

The configuration file is published to `config/oidc-client.php`. The config key is `oidc-client` (avoids collision with server-side `oidc` key when both packages are installed).

## Auth Server

| Key | Type | Env Var | Fallback Env | Default | Description |
|-----|------|---------|--------------|---------|-------------|
| `auth_server.host` | string | `OIDC_AUTH_SERVER_HOST` | `AUTH_SERVER_HOST` | `null` | Base URL of the OIDC provider |
| `auth_server.client_id` | string | `OIDC_CLIENT_ID` | `AUTH_SERVER_CLIENT_ID` | `null` | OAuth2 client ID |
| `auth_server.client_secret` | string | `OIDC_CLIENT_SECRET` | `AUTH_SERVER_CLIENT_SECRET` | `null` | OAuth2 client secret |
| `auth_server.redirect_uri` | string | `OIDC_REDIRECT_URI` | `AUTH_SERVER_REDIRECT` | `null` | Callback URL registered with the provider |

## General Settings

| Key | Type | Env Var | Default | Description |
|-----|------|---------|---------|-------------|
| `frontend_url` | string | `OIDC_FRONTEND_URL` / `FRONTEND_URL` | `http://localhost:3000` | Frontend app URL for post-auth redirects |
| `scopes` | string | `OIDC_SCOPES` | `openid profile email` | Space-separated OIDC scopes |
| `user_model` | string | `OIDC_USER_MODEL` | `App\Models\User` | Eloquent model class for users |
| `exchange_code_ttl` | int | `OIDC_EXCHANGE_CODE_TTL` | `5` | One-time exchange code lifetime in minutes |
| `jwt_guard` | string | `OIDC_JWT_GUARD` | `api` | Auth guard used for JWT token operations |

## User Mapping

Configured directly in PHP (not via env vars). Controls how OIDC userinfo claims map to your User model.

```php
'user_mapping' => [
    'identifier_column' => 'oidc_sub',                // DB column for OIDC subject ID
    'identifier_claim'  => 'sub',                      // Userinfo claim used as unique identifier
    'refresh_token_column' => 'auth_server_refresh_token', // DB column for refresh token
    'attributes' => [
        'name'  => fn ($userinfo) => $userinfo['name'] ?? $userinfo['email'],
        'email' => fn ($userinfo) => $userinfo['email'],
    ],
],
```

Each entry in `attributes` maps a DB column name to either a userinfo key (string) or a callable that receives the full userinfo array.

## Routes

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `routes.web.prefix` | string | `auth` | URL prefix for web routes (redirect, callback) |
| `routes.web.middleware` | array | `['web']` | Middleware for web routes |
| `routes.api.prefix` | string | `api/auth` | URL prefix for API routes (exchange) |
| `routes.api.middleware` | array | `['api']` | Middleware for API routes |

## Rate Limiting

| Key | Type | Env Var | Default | Description |
|-----|------|---------|---------|-------------|
| `rate_limits.exchange` | string | `OIDC_RATE_LIMIT_EXCHANGE` | `10,1` | Throttle rule for the exchange endpoint (requests,minutes) |

## HTTP Client

| Key | Type | Env Var | Default | Description |
|-----|------|---------|---------|-------------|
| `http.timeout` | int | `OIDC_HTTP_TIMEOUT` | `15` | Request timeout in seconds |
| `http.retry_times` | int | `OIDC_HTTP_RETRY_TIMES` | `2` | Number of retry attempts on failure |
| `http.retry_delay` | int | `OIDC_HTTP_RETRY_DELAY` | `200` | Delay between retries in milliseconds |

## Auth Server Endpoints

These paths are appended to `auth_server.host`. Configured directly in PHP.

| Key | Default | Description |
|-----|---------|-------------|
| `endpoints.authorize` | `/oauth/authorize` | Authorization endpoint |
| `endpoints.token` | `/oauth/token` | Token exchange endpoint |
| `endpoints.userinfo` | `/api/oauth/userinfo` | Userinfo endpoint |
| `endpoints.revoke` | `/oauth/revoke` | Token revocation endpoint |
| `endpoints.logout` | `/oauth/logout` | SSO logout endpoint |

## Complete Environment Variables Reference

### Required Variables

These variables **must** be set for the package to function:

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=http://localhost/auth/callback
```

### Recommended Variables

```env
OIDC_FRONTEND_URL=http://localhost:3000
```

### All Available Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `OIDC_AUTH_SERVER_HOST` | **Yes** | `null` | Base URL of your OIDC provider |
| `OIDC_CLIENT_ID` | **Yes** | `null` | OAuth2 client ID |
| `OIDC_CLIENT_SECRET` | **Yes** | `null` | OAuth2 client secret |
| `OIDC_REDIRECT_URI` | **Yes** | `null` | Callback URL (must match registered URI) |
| `OIDC_FRONTEND_URL` | No | `http://localhost:3000` | Frontend application URL |
| `OIDC_SCOPES` | No | `openid profile email` | Space-separated OIDC scopes |
| `OIDC_USER_MODEL` | No | `App\Models\User` | User model class |
| `OIDC_EXCHANGE_CODE_TTL` | No | `5` | Exchange code lifetime (minutes) |
| `OIDC_JWT_GUARD` | No | `api` | JWT auth guard name |
| `OIDC_WEB_GUARD` | No | `web` | Web session guard name |
| `OIDC_RATE_LIMIT_EXCHANGE` | No | `10,1` | Exchange endpoint rate limit |
| `OIDC_RATE_LIMIT_REDIRECT` | No | `5,1` | Redirect endpoint rate limit |
| `OIDC_RATE_LIMIT_CALLBACK` | No | `10,1` | Callback endpoint rate limit |
| `OIDC_HTTP_TIMEOUT` | No | `15` | HTTP timeout (seconds) |
| `OIDC_HTTP_RETRY_TIMES` | No | `2` | HTTP retry attempts |
| `OIDC_HTTP_RETRY_DELAY` | No | `200` | HTTP retry delay (ms) |

### Environment Examples

**Development:**

```env
OIDC_AUTH_SERVER_HOST=https://auth.dev.example.com
OIDC_CLIENT_ID=dev-client-id
OIDC_CLIENT_SECRET=dev-client-secret
OIDC_REDIRECT_URI=http://localhost:8000/auth/callback
OIDC_FRONTEND_URL=http://localhost:3000
OIDC_RATE_LIMIT_EXCHANGE=100,1
```

**Production:**

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=prod-client-id
OIDC_CLIENT_SECRET=prod-client-secret
OIDC_REDIRECT_URI=https://api.example.com/auth/callback
OIDC_FRONTEND_URL=https://app.example.com
OIDC_HTTP_TIMEOUT=20
OIDC_HTTP_RETRY_TIMES=3
```

## Minimal .env Example

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=my-app-client-id
OIDC_CLIENT_SECRET=my-app-client-secret
OIDC_REDIRECT_URI=https://api.example.com/auth/callback
OIDC_FRONTEND_URL=https://app.example.com
```

## See Also

- [Troubleshooting](troubleshooting.md) - Common issues and solutions

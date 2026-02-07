# Troubleshooting

Common errors encountered when using the OIDC client package, with causes and solutions.

## 1. Invalid State (403)

**Error:** `403 Invalid state` when the Auth Server redirects back to `/auth/callback`.

**Causes:**
- The session expired between the redirect and the callback. The `state` and `code_verifier` are stored in the Laravel session; if the session is lost, validation fails.
- The user opened the login link in a different browser or tab where the session does not exist.
- A load balancer is routing the callback to a different server that does not share the session store.
- The `SESSION_DRIVER` is set to `array` (non-persistent) in production.

**Solutions:**
- Ensure your session driver is persistent (`database`, `redis`, `file`) and not `array`.
- If using multiple servers, configure a shared session store (Redis, database).
- Check that `SESSION_DOMAIN` and `SESSION_SECURE_COOKIE` are set correctly for your domain.
- Verify the callback URL matches exactly what is configured in `OIDC_REDIRECT_URI`.

## 2. Token Exchange Failed

**Error:** The callback redirects to the frontend with `error=token_exchange_failed`.

**Causes:**
- The `client_id` or `client_secret` is incorrect.
- The `redirect_uri` sent to the Auth Server does not match the one registered for the client.
- The authorization code has already been used (codes are single-use).
- The PKCE `code_verifier` does not match the `code_challenge` (session corruption).

**Solutions:**
- Double-check `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, and `OIDC_REDIRECT_URI` in your `.env`.
- Ensure the redirect URI registered on the Auth Server matches `OIDC_REDIRECT_URI` exactly (including trailing slashes and protocol).
- Check the Auth Server logs for more specific error details.
- Verify the session is not being cleared between the redirect and callback steps.

## 3. Userinfo Fetch Failed

**Error:** The callback redirects to the frontend with `error=userinfo_failed`.

**Causes:**
- The access token returned by the Auth Server is invalid or expired.
- The userinfo endpoint path is misconfigured.
- The Auth Server requires additional scopes to access userinfo.

**Solutions:**
- Verify `endpoints.userinfo` in the config matches your Auth Server's userinfo endpoint.
- Ensure the requested scopes (`OIDC_SCOPES`) include `openid` and any other scopes required by your provider.
- Check Auth Server logs for token validation errors.

## 4. Auth Server Unreachable

**Error:** The callback redirects to the frontend with `error=server_unreachable`.

**Causes:**
- The Auth Server host is down or unreachable from the Laravel application server.
- DNS resolution failure for the Auth Server hostname.
- Firewall rules blocking outbound HTTP requests from the Laravel server.
- The `OIDC_AUTH_SERVER_HOST` value is incorrect.

**Solutions:**
- Verify the Auth Server is running: `curl -I https://auth.example.com`.
- Check `OIDC_AUTH_SERVER_HOST` is correct and includes the protocol (`https://`).
- Ensure the Laravel server can reach the Auth Server (check firewall, security groups, DNS).
- Increase `OIDC_HTTP_TIMEOUT` if the Auth Server is slow to respond.
- Increase `OIDC_HTTP_RETRY_TIMES` for transient network issues.

## 5. Exchange Code Expired or Invalid

**Error:** `POST /api/auth/exchange` returns `401` with `"Invalid or expired exchange code"`.

**Causes:**
- The exchange code has expired (default TTL is 5 minutes).
- The exchange code was already used (codes are one-time use, pulled from cache on first use).
- The cache driver is not persistent (e.g., `array` driver in production).
- The frontend sent a malformed or incorrect code.

**Solutions:**
- Ensure the frontend calls the exchange endpoint promptly after receiving the code.
- Increase `OIDC_EXCHANGE_CODE_TTL` if 5 minutes is too short for your use case.
- Use a persistent cache driver (`redis`, `database`, `file`) -- not `array`.
- Verify the frontend is sending the code exactly as received (UUID format).
- Check that the frontend is not calling the exchange endpoint twice.

## 6. Rate Limit Exceeded on Exchange

**Error:** `429 Too Many Requests` on `POST /api/auth/exchange`.

**Causes:**
- The frontend is retrying the exchange request too aggressively.
- Multiple users share the same IP and hit the rate limit collectively.

**Solutions:**
- Increase the rate limit via `OIDC_RATE_LIMIT_EXCHANGE` (e.g., `30,1` for 30 requests per minute).
- Ensure the frontend does not retry on success or after receiving a 401.

### Understanding Rate Limiting

The package applies rate limiting to all OIDC endpoints to prevent abuse:

| Endpoint | Default Limit | Environment Variable |
|----------|---------------|---------------------|
| `/auth/redirect` | 5 requests/minute | `OIDC_RATE_LIMIT_REDIRECT` |
| `/auth/callback` | 10 requests/minute | `OIDC_RATE_LIMIT_CALLBACK` |
| `/api/auth/exchange` | 10 requests/minute | `OIDC_RATE_LIMIT_EXCHANGE` |

**What happens when rate limit is exceeded:**
- The endpoint returns HTTP `429 Too Many Requests`
- The response includes a `Retry-After` header indicating when to retry
- Laravel logs the rate limit hit (check `storage/logs/laravel.log`)

**How to customize rate limits:**

Add to your `.env`:
```env
# Format: requests,minutes
OIDC_RATE_LIMIT_EXCHANGE=20,1    # 20 requests per minute
OIDC_RATE_LIMIT_REDIRECT=10,1    # 10 requests per minute
OIDC_RATE_LIMIT_CALLBACK=20,1    # 20 requests per minute
```

**How to monitor rate limit hits:**

Listen to Laravel's rate limiting events or check logs:
```php
// In a service provider
RateLimiter::hit('oidc.exchange');  // Logged automatically by Laravel
```

**Best practices:**
- Set limits based on your expected traffic patterns
- Monitor logs for legitimate users hitting limits
- Implement exponential backoff in frontend retry logic
- Consider per-user rate limiting instead of per-IP for authenticated endpoints

## 7. User Model Errors

**Error:** `MassAssignmentException` or missing column errors during callback.

**Causes:**
- The OIDC columns (`oidc_sub`, `auth_server_refresh_token`) are not in the model's `$fillable` array.
- The migration has not been run.
- The `user_mapping.attributes` config references columns that do not exist in the database.

**Solutions:**
- Add all OIDC columns to `$fillable` on your User model.
- Run `php artisan migrate` to ensure the migration has been applied.
- Verify `user_mapping.attributes` keys match actual database column names.

## 8. Cache Driver Requirements

**Error:** Exchange codes expire immediately or state validation fails randomly.

**Causes:**
- Using a non-persistent cache driver (e.g., `array`) in production.
- Cache is being cleared between OIDC flow steps.
- Multiple application servers not sharing the same cache store.

**Why persistent cache is required:**

The OIDC flow stores critical data in Laravel's cache:
1. **PKCE code_verifier** - Stored during redirect, retrieved during callback (lifespan: ~1-5 minutes)
2. **State parameter** - Stored during redirect, validated during callback (lifespan: ~1-5 minutes)
3. **Exchange codes** - Stored during callback, consumed during exchange (lifespan: 5 minutes by default)

If the cache is not persistent, this data is lost and the flow fails.

**What breaks with array driver:**
- ✗ `array` driver stores data in memory only (lost between requests)
- ✗ State validation fails with "Invalid state" error
- ✗ Exchange codes are immediately "expired or invalid"
- ✗ PKCE verification fails during token exchange

**Recommended cache drivers:**

| Driver | Use Case | Configuration |
|--------|----------|---------------|
| `redis` | **Production (recommended)** | Fast, persistent, supports multiple servers |
| `database` | Production | Persistent, no additional dependencies |
| `file` | Development/Single-server | Persistent, simple setup |
| `memcached` | Production | Fast, persistent, supports multiple servers |
| `array` | **Testing only** | Non-persistent, DO NOT use in production |

**How to configure cache driver:**

In `.env`:
```env
CACHE_DRIVER=redis  # or database, file, memcached
```

For Redis:
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

For Database:
```bash
php artisan cache:table
php artisan migrate
```

```env
CACHE_DRIVER=database
```

**Verifying cache persistence:**

Test that cache persists between requests:
```bash
php artisan tinker
>>> cache()->put('test', 'value', 60);
>>> exit

php artisan tinker
>>> cache()->get('test');  // Should return "value"
```

If `cache()->get('test')` returns `null`, your cache driver is not persistent.

**Multi-server deployments:**

If running multiple application servers (load balanced):
- Use `redis` or `database` cache driver (NOT `file`)
- All servers must connect to the same Redis/database instance
- Verify cache is shared: set a value on server A, read it from server B

## Debugging Tips

- Listen to the `OidcAuthFailed` event to capture error codes and messages for logging or monitoring.
- Use `php artisan route:list` to verify the OIDC routes are registered correctly.
- Test the Auth Server connection: `curl -s https://auth.example.com/oauth/authorize | head`.

## Frequently Asked Questions

### What is OIDC?

OIDC (OpenID Connect) is an authentication protocol built on top of OAuth 2.0. It allows applications to verify user identity and obtain basic profile information from an external identity provider.

### Do I need an external OIDC provider?

Yes. This package is a **client** that connects to an external OIDC provider (Auth Server). You need Keycloak, Auth0, Okta, or a custom OAuth2/OIDC server.

### Can I use the `array` cache driver?

**No.** The `array` driver is non-persistent and loses data between requests. Use `redis`, `database`, or `file` instead.

### Why does my session keep expiring?

Check your session driver. The `array` driver is non-persistent. Use `database`, `redis`, or `file`.

### How do I implement logout?

Create a logout controller using `OidcService`:

```php
public function logout(Request $request, OidcService $oidcService): JsonResponse
{
    $user = $request->user();
    $oidcService->revokeAuthServerToken($user);
    auth('api')->logout();

    $data = ['message' => 'Logged out'];
    if ($oidcService->isOidcUser($user)) {
        $data['logout_url'] = $oidcService->getSsoLogoutUrl();
    }

    return response()->json($data);
}
```

### Should I use HTTPS in production?

**Absolutely yes.** All URLs should use HTTPS in production.

### Can users have both OIDC and password authentication?

Yes. Users can have OIDC only, password only, or both.

### Are users created automatically?

Yes. When a user authenticates via OIDC for the first time, the package automatically creates a User record.

### How long does the JWT token last?

That's configured in your JWT package (`php-open-source-saver/jwt-auth`), not this package. Check `config/jwt.php`.

### Can I customize rate limits?

Yes, in `.env`:

```env
OIDC_RATE_LIMIT_EXCHANGE=20,1
OIDC_RATE_LIMIT_REDIRECT=10,1
OIDC_RATE_LIMIT_CALLBACK=20,1
```

## See Also

- [Configuration Reference](configuration.md) - All config options and environment variables

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

**Error:** After callback, the user is redirected with `oidc_error=auth_failed` flashed to the session.

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

**Error:** After callback, the user is redirected with `oidc_error=auth_failed` flashed to the session.

**Causes:**
- The access token returned by the Auth Server is invalid or expired.
- The userinfo endpoint path is misconfigured.
- The Auth Server requires additional scopes to access userinfo.

**Solutions:**
- Verify `endpoints.userinfo` in the config matches your Auth Server's userinfo endpoint.
- Ensure the requested scopes (`OIDC_SCOPES`) include `openid` and any other scopes required by your provider.
- Check Auth Server logs for token validation errors.

## 4. Auth Server Unreachable

**Error:** After callback, the user is redirected with `oidc_error=server_unreachable` flashed to the session.

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

## 5. User Model Errors

**Error:** `MassAssignmentException` or missing column errors during callback.

**Causes:**
- The OIDC columns (`oidc_sub`, `auth_server_refresh_token`) are not in the model's `$fillable` array.
- The migration has not been run.
- The `user_mapping.attributes` config references columns that do not exist in the database.

**Solutions:**
- Add all OIDC columns to `$fillable` on your User model.
- Run `php artisan migrate` to ensure the migration has been applied.
- Verify `user_mapping.attributes` keys match actual database column names.

## 6. Session Driver Requirements

**Error:** State validation fails randomly or login sessions are lost.

**Causes:**
- Using a non-persistent session driver (e.g., `array`) in production.
- Session is being cleared between OIDC flow steps.
- Multiple application servers not sharing the same session store.

**Solutions:**
- Use a persistent session driver (`redis`, `database`, `file`) — not `array`.
- For multi-server deployments, use `redis` or `database` and ensure all servers share the same store.

## Debugging Tips

- Listen to the `OidcAuthFailed` event to capture error codes and messages for logging or monitoring.
- Check `session('oidc_error')` and `session('oidc_error_description')` on your redirect target page to display errors.
- Use `php artisan route:list` to verify the OIDC routes are registered correctly.
- Test the Auth Server connection: `curl -s https://auth.example.com/oauth/authorize | head`.

## Frequently Asked Questions

### What is OIDC?

OIDC (OpenID Connect) is an authentication protocol built on top of OAuth 2.0. It allows applications to verify user identity and obtain basic profile information from an external identity provider.

### Do I need an external OIDC provider?

Yes. This package is a **client** that connects to an external OIDC provider (Auth Server). You need Keycloak, Auth0, Okta, or a custom OAuth2/OIDC server.

### Why does my session keep expiring?

Check your session driver. The `array` driver is non-persistent. Use `database`, `redis`, or `file`.

### How do I implement logout?

Create a logout controller using `OidcService`:

```php
public function logout(Request $request, OidcService $oidcService)
{
    $user = $request->user();
    $oidcService->revokeAuthServerToken($user);

    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($oidcService->isOidcUser($user)) {
        return redirect($oidcService->getSsoLogoutUrl());
    }

    return redirect('/');
}
```

### Should I use HTTPS in production?

**Absolutely yes.** All URLs should use HTTPS in production.

### Can users have both OIDC and password authentication?

Yes. Users can have OIDC only, password only, or both.

### Are users created automatically?

Yes. When a user authenticates via OIDC for the first time, the package automatically creates a User record.

### Can I customize rate limits?

Yes, in `.env`:

```env
OIDC_RATE_LIMIT_REDIRECT=10,1
OIDC_RATE_LIMIT_CALLBACK=20,1
```

## See Also

- [Configuration Reference](configuration.md) - All config options and environment variables

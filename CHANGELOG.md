# Changelog

All notable changes to `laravel-oidc-client` will be documented in this file.

## 1.2.0 - Unreleased

### Changed
- Removed `"admin9"` from composer.json keywords.
- Refactored controller to use `OidcService` for user provisioning (`findOrCreateUser`).
- Fixed hardcoded `$user->email` / `$user->oidc_sub` in log — now uses configured `identifier_column`.
- `Auth::login()` now uses configurable `web_guard` (default: `web`).
- Renamed route `auth.exchange` to `oidc.exchange` for consistent naming.
- Removed nonce parameter from authorization request (was stored but never validated).

### Added
- `OidcService::findOrCreateUser()` method for user provisioning from OIDC userinfo.
- Event system: `OidcUserAuthenticated`, `OidcTokenExchanged`, `OidcAuthFailed`.
- `web_guard` config option for customizable web session guard.
- Response validation in `OidcService` (checks for `access_token` and `identifier_claim`).

### Removed
- `OidcClientUserInterface` contract (was unused).

## 1.1.0 - Unreleased

### Changed
- **BREAKING**: Config key renamed from `oidc` to `oidc-client` to avoid collision when both server and client packages are installed. Update any published config file and `config('oidc.*')` references to `config('oidc-client.*')`.
- Translation keys changed from `__('auth.*')` to `__('oidc-client::messages.*')` — the package now ships its own translation files.

### Added
- `user_mapping` config for customizable identifier column, identifier claim, refresh token column, and userinfo→attribute mapping.
- Translation files (`resources/lang/en/messages.php`) with publishable translations for error messages.

## 1.0.0 - Unreleased

- OIDC Authorization Code Flow with PKCE
- State parameter validation for CSRF protection
- One-time exchange codes for SPA JWT bridge
- OidcService for token revocation and SSO logout
- Configurable routes, middleware, and rate limiting
- Migration for `oidc_sub` and `auth_server_refresh_token` fields

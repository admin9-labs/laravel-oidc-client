# Changelog

All notable changes to `laravel-oidc-client` will be documented in this file.

## v1.0.3 - 2026-02-08

### Changed

- **Migration field positioning**: Moved OIDC fields (`oidc_sub` and `auth_server_refresh_token`) to appear after `remember_token` instead of after `id` for better table structure organization

**Note**: This is a migration-only change. If you've already published and run the migration, this won't affect existing tables.

## v1.0.2 - 2026-02-08

### Documentation

- **Drastically simplified documentation structure**: Reduced from 8 files to 3 essential files
  - Removed verbose and unnecessary documentation (guide.md, advanced.md, api-reference.md, security.md, user-mapping.md)
  - Removed CONTRIBUTING.md and SECURITY.md (use GitHub issues instead)
  - Kept only essential docs: README.md, configuration.md, troubleshooting.md

- **Improved README.md**:
  - Removed limiting "designed for SPAs" language
  - Added complete frontend integration examples (login, callback, JWT usage)
  - Focused on quick start and practical usage
  - Streamlined to help developers get started quickly

- **Updated Chinese translations**: All Chinese docs updated to match simplified structure
- **Fixed cross-references**: Updated all documentation links to reference only existing files

**Note**: This is a documentation-only release. No code changes or breaking changes.

## v1.0.1 - Complete Chinese Documentation - 2026-02-07

### 📚 Documentation Improvements

This release focuses on comprehensive documentation enhancements, making the package accessible to Chinese-speaking developers and improving overall documentation quality.

#### ✨ What's New

##### Complete Chinese Translation (zh-CN)

- **Full bilingual support**: All 8 core documentation files now available in Chinese
- **Language switcher**: Easy navigation between English and Chinese versions
- **Organized structure**: All Chinese docs in `docs/zh-CN/` directory

**Chinese Documentation:**

- 📖 [完整指南](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/guide.md) - Authentication flow and frontend integration
- ⚙️ [配置参考](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/configuration.md) - All configuration options
- 🔧 [API 参考](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/api-reference.md) - OidcService methods, events, exceptions
- 🗺️ [用户映射](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/user-mapping.md) - Custom claim mapping
- 🔒 [安全特性](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/security.md) - Security features and best practices
- 🚀 [高级主题](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/advanced.md) - Routes, multi-tenant, testing
- 🔍 [故障排除](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/troubleshooting.md) - Common issues and FAQ

##### Documentation Consolidation

- **Streamlined structure**: Reduced from 18 to 8 documentation files
- **Better organization**: Related topics merged for improved readability
- **Progressive disclosure**: Documentation follows a clear learning path from basics to advanced topics

#### 📦 Installation

```bash
composer require admin9/laravel-oidc-client:^1.0.1

```
#### 🔗 Quick Links

- [English Documentation](https://github.com/admin9-labs/laravel-oidc-client/blob/main/README.md)
- [中文文档](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/zh-CN/README.md)
- [Complete Guide](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/guide.md)
- [Configuration Reference](https://github.com/admin9-labs/laravel-oidc-client/blob/main/docs/configuration.md)

#### 📝 Full Changelog

See [CHANGELOG.md](https://github.com/admin9-labs/laravel-oidc-client/blob/main/CHANGELOG.md) for complete details.


---

**Note**: This is a documentation-only release. No code changes or breaking changes.

## v1.0.1 - 2026-02-08

### Documentation

- **Complete Chinese translation (zh-CN)**: Added full Chinese documentation for all 8 core docs
  - `docs/zh-CN/README.md` - Main README in Chinese
  - `docs/zh-CN/guide.md` - Authentication flow and frontend integration
  - `docs/zh-CN/configuration.md` - All configuration options
  - `docs/zh-CN/api-reference.md` - OidcService methods, events, exceptions
  - `docs/zh-CN/troubleshooting.md` - Common issues and FAQ
  - `docs/zh-CN/user-mapping.md` - Custom claim mapping
  - `docs/zh-CN/security.md` - Security features and best practices
  - `docs/zh-CN/advanced.md` - Routes, multi-tenant, testing
  
- **Documentation consolidation**: Reduced from 18 to 8 documentation files for better organization
  - Merged related topics (authentication-flow + frontend-integration → guide.md)
  - Merged API docs (oidc-service + events + exceptions → api-reference.md)
  - Merged advanced topics (routes + multi-tenant + testing → advanced.md)
  - Enhanced configuration.md with environment variables
  - Enhanced troubleshooting.md with FAQ content
  
- **Language switcher**: Added bilingual navigation in both English and Chinese READMEs
- All code examples preserved in English (standard practice)
- Improved documentation structure following progressive disclosure pattern

## v1.0.0 - 2026-02-07

### Initial Release

#### Features

- OIDC Authorization Code Flow with PKCE
- State parameter validation for CSRF protection
- One-time exchange codes for SPA JWT bridge
- `OidcService` for token exchange, user provisioning, revocation, and SSO logout
- Configurable `user_mapping` (identifier column, identifier claim, refresh token column, userinfo→attribute mapping)
- Configurable `web_guard` for web session guard
- Event system: `OidcUserAuthenticated`, `OidcTokenExchanged`, `OidcAuthFailed`
- Publishable config (`config/oidc-client.php`) and translations
- Configurable routes, middleware, and rate limiting

#### Requirements

- PHP 8.2+
- Laravel 11/12

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

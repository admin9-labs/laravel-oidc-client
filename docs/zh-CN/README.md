# Laravel OIDC 客户端

[![Packagist 最新版本](https://img.shields.io/packagist/v/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)
[![GitHub 测试状态](https://img.shields.io/github/actions/workflow/status/admin9-labs/laravel-oidc-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/admin9-labs/laravel-oidc-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![总下载量](https://img.shields.io/packagist/dt/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)

[English](../../README.md) | 简体中文

一个支持 PKCE 的 Laravel OIDC（OpenID Connect）认证包。架构无关 - 适用于 Blade、Livewire、Inertia 或任何 Laravel 技术栈。

## 功能特性

- OIDC 授权码流程 + PKCE
- 从 OIDC 声明自动创建/更新用户
- 灵活的用户映射配置
- 令牌撤销和 SSO 登出支持
- 所有端点速率限制
- 认证生命周期事件系统

## 系统要求

- PHP 8.2+
- Laravel 11.x 或 12.x
- 持久化会话驱动（redis、database、file）

## 安装

```bash
composer require admin9/laravel-oidc-client
php artisan vendor:publish --tag="oidc-client-config"
php artisan vendor:publish --tag="oidc-client-migrations"
php artisan migrate
```

## 配置

在 `.env` 中添加：

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=http://localhost:8000/auth/callback
```

更新 `app/Models/User.php`：

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

## 使用

### 路由

包注册了以下路由：

| 方法 | URI | 描述 |
|--------|-----|-------------|
| GET | `/auth/redirect` | 启动 OIDC 流程 |
| GET | `/auth/callback` | 处理回调、创建会话、重定向 |

### 工作原理

1. 用户访问 `/auth/redirect` — 重定向到 OIDC 提供商
2. 认证后，提供商重定向回 `/auth/callback`
3. 包交换授权码获取令牌，获取用户信息，创建/更新本地用户
4. 用户通过 Laravel Web 会话守卫登录，重定向到配置的 `redirect_url`（默认：`/dashboard`）

### 登录链接

```html
<a href="/auth/redirect">SSO 登录</a>
```

### 错误处理

认证错误会闪存到会话中：

```php
@if (session('oidc_error'))
    <div class="alert alert-danger">
        认证失败：{{ session('oidc_error_description') }}
    </div>
@endif
```

### 登出

使用 `OidcService` 创建登出控制器：

```php
use Admin9\OidcClient\Services\OidcService;

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

### 可选配置

```env
OIDC_REDIRECT_URL=/dashboard              # 登录后重定向地址（默认：/dashboard）
OIDC_POST_LOGOUT_REDIRECT_URL=/           # Auth Server SSO 登出后重定向地址（默认：/）
OIDC_WEB_GUARD=web                        # 会话登录的认证守卫（默认：web）
```

## 文档

- [配置参考](configuration.md) - 所有配置选项和环境变量
- [故障排除](troubleshooting.md) - 常见问题和解决方案

## 许可证

MIT

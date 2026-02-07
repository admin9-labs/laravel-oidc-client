# Laravel OIDC 客户端

[![Packagist 最新版本](https://img.shields.io/packagist/v/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)
[![GitHub 测试状态](https://img.shields.io/github/actions/workflow/status/admin9-labs/laravel-oidc-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/admin9-labs/laravel-oidc-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![总下载量](https://img.shields.io/packagist/dt/admin9/laravel-oidc-client.svg?style=flat-square)](https://packagist.org/packages/admin9/laravel-oidc-client)

[English](../../README.md) | 简体中文

一个支持 PKCE 的 Laravel OIDC（OpenID Connect）认证包。

## 功能特性

- ✅ OIDC 授权码流程 + PKCE
- ✅ 从 OIDC 声明自动创建/更新用户
- ✅ 灵活的用户映射配置
- ✅ 令牌撤销支持
- ✅ 所有端点速率限制
- ✅ 认证生命周期事件系统

## 系统要求

- PHP 8.2+
- Laravel 11.x 或 12.x
- JWT 包：[`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth) 或 [`tymon/jwt-auth`](https://github.com/tymondesigns/jwt-auth)
- 持久化缓存驱动（redis、database、file）
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
OIDC_FRONTEND_URL=http://localhost:3000
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

### 后端路由

包注册了以下路由：

| 方法 | URI | 描述 |
|--------|-----|-------------|
| GET | `/auth/redirect` | 启动 OIDC 流程 |
| GET | `/auth/callback` | 处理回调 |
| POST | `/api/auth/exchange` | 交换码换取 JWT |

### 前端集成

#### 1. 登录

重定向开始 OIDC 流程：

```javascript
window.location.href = 'http://localhost:8000/auth/redirect';
```

#### 2. 回调

在前端处理回调：

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

#### 3. 使用 JWT

使用令牌进行认证请求：

```javascript
fetch('http://localhost:8000/api/user', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
  },
});
```

## 文档

- [配置参考](configuration.md) - 所有配置选项和环境变量
- [故障排除](troubleshooting.md) - 常见问题和解决方案

## 许可证

MIT

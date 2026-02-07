# 配置参考

配置文件发布到 `config/oidc-client.php`。配置键为 `oidc-client`（避免与服务器端 `oidc` 键冲突，当两个包同时安装时）。

## Auth Server

| 键 | 类型 | 环境变量 | 备用环境变量 | 默认值 | 描述 |
|-----|------|---------|--------------|---------|-------------|
| `auth_server.host` | string | `OIDC_AUTH_SERVER_HOST` | `AUTH_SERVER_HOST` | `null` | OIDC 提供商的基础 URL |
| `auth_server.client_id` | string | `OIDC_CLIENT_ID` | `AUTH_SERVER_CLIENT_ID` | `null` | OAuth2 客户端 ID |
| `auth_server.client_secret` | string | `OIDC_CLIENT_SECRET` | `AUTH_SERVER_CLIENT_SECRET` | `null` | OAuth2 客户端密钥 |
| `auth_server.redirect_uri` | string | `OIDC_REDIRECT_URI` | `AUTH_SERVER_REDIRECT` | `null` | 在提供商注册的回调 URL |

## 常规设置

| 键 | 类型 | 环境变量 | 默认值 | 描述 |
|-----|------|---------|---------|-------------|
| `frontend_url` | string | `OIDC_FRONTEND_URL` / `FRONTEND_URL` | `http://localhost:3000` | 前端应用 URL，用于认证后重定向 |
| `scopes` | string | `OIDC_SCOPES` | `openid profile email` | 空格分隔的 OIDC 作用域 |
| `user_model` | string | `OIDC_USER_MODEL` | `App\\Models\\User` | 用户的 Eloquent 模型类 |
| `exchange_code_ttl` | int | `OIDC_EXCHANGE_CODE_TTL` | `5` | 一次性交换码的生命周期（分钟） |
| `jwt_guard` | string | `OIDC_JWT_GUARD` | `api` | 用于 JWT 令牌操作的认证守卫 |

## 用户映射

直接在 PHP 中配置（不通过环境变量）。控制 OIDC 用户信息声明如何映射到你的用户模型。

```php
'user_mapping' => [
    'identifier_column' => 'oidc_sub',                // OIDC 主体 ID 的数据库列
    'identifier_claim'  => 'sub',                      // 用作唯一标识符的用户信息声明
    'refresh_token_column' => 'auth_server_refresh_token', // 刷新令牌的数据库列
    'attributes' => [
        'name'  => fn ($userinfo) => $userinfo['name'] ?? $userinfo['email'],
        'email' => fn ($userinfo) => $userinfo['email'],
    ],
],
```

`attributes` 中的每个条目将数据库列名映射到用户信息键（字符串）或接收完整用户信息数组的可调用对象。

## 路由

| 键 | 类型 | 默认值 | 描述 |
|-----|------|---------|-------------|
| `routes.web.prefix` | string | `auth` | Web 路由的 URL 前缀（redirect、callback） |
| `routes.web.middleware` | array | `['web']` | Web 路由的中间件 |
| `routes.api.prefix` | string | `api/auth` | API 路由的 URL 前缀（exchange） |
| `routes.api.middleware` | array | `['api']` | API 路由的中间件 |

## 速率限制

| 键 | 类型 | 环境变量 | 默认值 | 描述 |
|-----|------|---------|---------|-------------|
| `rate_limits.exchange` | string | `OIDC_RATE_LIMIT_EXCHANGE` | `10,1` | 交换端点的限流规则（请求数,分钟数） |
| `rate_limits.redirect` | string | `OIDC_RATE_LIMIT_REDIRECT` | `5,1` | 重定向端点的限流规则 |
| `rate_limits.callback` | string | `OIDC_RATE_LIMIT_CALLBACK` | `10,1` | 回调端点的限流规则 |

## HTTP 客户端

| 键 | 类型 | 环境变量 | 默认值 | 描述 |
|-----|------|---------|---------|-------------|
| `http.timeout` | int | `OIDC_HTTP_TIMEOUT` | `15` | 请求超时时间（秒） |
| `http.retry_times` | int | `OIDC_HTTP_RETRY_TIMES` | `2` | 失败时的重试次数 |
| `http.retry_delay` | int | `OIDC_HTTP_RETRY_DELAY` | `200` | 重试之间的延迟（毫秒） |

## Auth Server 端点

这些路径会附加到 `auth_server.host`。直接在 PHP 中配置。

| 键 | 默认值 | 描述 |
|-----|---------|-------------|
| `endpoints.authorize` | `/oauth/authorize` | 授权端点 |
| `endpoints.token` | `/oauth/token` | 令牌交换端点 |
| `endpoints.userinfo` | `/api/oauth/userinfo` | 用户信息端点 |
| `endpoints.revoke` | `/oauth/revoke` | 令牌撤销端点 |
| `endpoints.logout` | `/oauth/logout` | SSO 登出端点 |

## 完整环境变量参考

### 必需变量

这些变量**必须**设置才能使包正常工作：

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=your-client-id
OIDC_CLIENT_SECRET=your-client-secret
OIDC_REDIRECT_URI=http://localhost/auth/callback
```

### 推荐变量

```env
OIDC_FRONTEND_URL=http://localhost:3000
```

### 所有可用变量

| 变量 | 必需 | 默认值 | 描述 |
|----------|----------|---------|-------------|
| `OIDC_AUTH_SERVER_HOST` | **是** | `null` | OIDC 提供商的基础 URL |
| `OIDC_CLIENT_ID` | **是** | `null` | OAuth2 客户端 ID |
| `OIDC_CLIENT_SECRET` | **是** | `null` | OAuth2 客户端密钥 |
| `OIDC_REDIRECT_URI` | **是** | `null` | 回调 URL（必须匹配注册的 URI） |
| `OIDC_FRONTEND_URL` | 否 | `http://localhost:3000` | 前端应用 URL |
| `OIDC_SCOPES` | 否 | `openid profile email` | 空格分隔的 OIDC 作用域 |
| `OIDC_USER_MODEL` | 否 | `App\\Models\\User` | 用户模型类 |
| `OIDC_EXCHANGE_CODE_TTL` | 否 | `5` | 交换码生命周期（分钟） |
| `OIDC_JWT_GUARD` | 否 | `api` | JWT 认证守卫名称 |
| `OIDC_WEB_GUARD` | 否 | `web` | Web 会话守卫名称 |
| `OIDC_RATE_LIMIT_EXCHANGE` | 否 | `10,1` | 交换端点速率限制 |
| `OIDC_RATE_LIMIT_REDIRECT` | 否 | `5,1` | 重定向端点速率限制 |
| `OIDC_RATE_LIMIT_CALLBACK` | 否 | `10,1` | 回调端点速率限制 |
| `OIDC_HTTP_TIMEOUT` | 否 | `15` | HTTP 超时（秒） |
| `OIDC_HTTP_RETRY_TIMES` | 否 | `2` | HTTP 重试次数 |
| `OIDC_HTTP_RETRY_DELAY` | 否 | `200` | HTTP 重试延迟（毫秒） |

### 环境示例

**开发环境：**

```env
OIDC_AUTH_SERVER_HOST=https://auth.dev.example.com
OIDC_CLIENT_ID=dev-client-id
OIDC_CLIENT_SECRET=dev-client-secret
OIDC_REDIRECT_URI=http://localhost:8000/auth/callback
OIDC_FRONTEND_URL=http://localhost:3000
OIDC_RATE_LIMIT_EXCHANGE=100,1
```

**生产环境：**

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=prod-client-id
OIDC_CLIENT_SECRET=prod-client-secret
OIDC_REDIRECT_URI=https://api.example.com/auth/callback
OIDC_FRONTEND_URL=https://app.example.com
OIDC_HTTP_TIMEOUT=20
OIDC_HTTP_RETRY_TIMES=3
```

## 最小 .env 示例

```env
OIDC_AUTH_SERVER_HOST=https://auth.example.com
OIDC_CLIENT_ID=my-app-client-id
OIDC_CLIENT_SECRET=my-app-client-secret
OIDC_REDIRECT_URI=https://api.example.com/auth/callback
OIDC_FRONTEND_URL=https://app.example.com
```

## 另请参阅

- [故障排除](troubleshooting.md) - 常见问题和解决方案

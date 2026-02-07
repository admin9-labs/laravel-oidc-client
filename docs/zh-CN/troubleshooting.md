# 故障排除

使用 OIDC 客户端包时遇到的常见错误及其原因和解决方案。

## 1. 无效状态 (403)

**错误：** 当 Auth Server 重定向回 `/auth/callback` 时出现 `403 Invalid state`。

**原因：**
- 会话在重定向和回调之间过期。`state` 和 `code_verifier` 存储在 Laravel 会话中；如果会话丢失，验证将失败。
- 用户在不同的浏览器或标签页中打开了登录链接，该会话不存在。
- 负载均衡器将回调路由到不共享会话存储的不同服务器。
- `SESSION_DRIVER` 在生产环境中设置为 `array`（非持久化）。

**解决方案：**
- 确保你的会话驱动是持久化的（`database`、`redis`、`file`）而不是 `array`。
- 如果使用多台服务器，配置共享会话存储（Redis、数据库）。
- 检查 `SESSION_DOMAIN` 和 `SESSION_SECURE_COOKIE` 是否为你的域名正确设置。
- 验证回调 URL 与 `OIDC_REDIRECT_URI` 中配置的完全匹配。

## 2. 令牌交换失败

**错误：** 回调重定向到前端时带有 `error=token_exchange_failed`。

**原因：**
- `client_id` 或 `client_secret` 不正确。
- 发送到 Auth Server 的 `redirect_uri` 与为客户端注册的不匹配。
- 授权码已被使用（代码是一次性使用的）。
- PKCE `code_verifier` 与 `code_challenge` 不匹配（会话损坏）。

**解决方案：**
- 仔细检查 `.env` 中的 `OIDC_CLIENT_ID`、`OIDC_CLIENT_SECRET` 和 `OIDC_REDIRECT_URI`。
- 确保在 Auth Server 上注册的重定向 URI 与 `OIDC_REDIRECT_URI` 完全匹配（包括尾部斜杠和协议）。
- 检查 Auth Server 日志以获取更具体的错误详情。
- 验证会话在重定向和回调步骤之间没有被清除。

## 3. 用户信息获取失败

**错误：** 回调重定向到前端时带有 `error=userinfo_failed`。

**原因：**
- Auth Server 返回的访问令牌无效或已过期。
- 用户信息端点路径配置错误。
- Auth Server 需要额外的作用域才能访问用户信息。

**解决方案：**
- 验证配置中的 `endpoints.userinfo` 与你的 Auth Server 的用户信息端点匹配。
- 确保请求的作用域（`OIDC_SCOPES`）包含 `openid` 以及提供商要求的任何其他作用域。
- 检查 Auth Server 日志以查找令牌验证错误。

## 4. Auth Server 不可达

**错误：** 回调重定向到前端时带有 `error=server_unreachable`。

**原因：**
- Auth Server 主机已关闭或从 Laravel 应用服务器无法访问。
- Auth Server 主机名的 DNS 解析失败。
- 防火墙规则阻止了来自 Laravel 服务器的出站 HTTP 请求。
- `OIDC_AUTH_SERVER_HOST` 值不正确。

**解决方案：**
- 验证 Auth Server 正在运行：`curl -I https://auth.example.com`。
- 检查 `OIDC_AUTH_SERVER_HOST` 是否正确并包含协议（`https://`）。
- 确保 Laravel 服务器可以访问 Auth Server（检查防火墙、安全组、DNS）。
- 如果 Auth Server 响应缓慢，增加 `OIDC_HTTP_TIMEOUT`。
- 对于瞬态网络问题，增加 `OIDC_HTTP_RETRY_TIMES`。

## 5. 交换码过期或无效

**错误：** `POST /api/auth/exchange` 返回 `401`，消息为 `"Invalid or expired exchange code"`。

**原因：**
- 交换码已过期（默认 TTL 为 5 分钟）。
- 交换码已被使用（代码是一次性使用的，首次使用时从缓存中拉取）。
- 缓存驱动不是持久化的（例如，生产环境中的 `array` 驱动）。
- 前端发送了格式错误或不正确的代码。

**解决方案：**
- 确保前端在收到代码后立即调用交换端点。
- 如果 5 分钟对你的用例来说太短，增加 `OIDC_EXCHANGE_CODE_TTL`。
- 使用持久化缓存驱动（`redis`、`database`、`file`）-- 不要使用 `array`。
- 验证前端发送的代码与收到的完全一致（UUID 格式）。
- 检查前端是否没有两次调用交换端点。

## 6. 交换端点速率限制超出

**错误：** `POST /api/auth/exchange` 返回 `429 Too Many Requests`。

**原因：**
- 前端过于激进地重试交换请求。
- 多个用户共享同一 IP 并集体达到速率限制。

**解决方案：**
- 通过 `OIDC_RATE_LIMIT_EXCHANGE` 增加速率限制（例如，`30,1` 表示每分钟 30 个请求）。
- 确保前端在成功或收到 401 后不会重试。

### 理解速率限制

包对所有 OIDC 端点应用速率限制以防止滥用：

| 端点 | 默认限制 | 环境变量 |
|----------|---------------|---------------------|
| `/auth/redirect` | 每分钟 5 个请求 | `OIDC_RATE_LIMIT_REDIRECT` |
| `/auth/callback` | 每分钟 10 个请求 | `OIDC_RATE_LIMIT_CALLBACK` |
| `/api/auth/exchange` | 每分钟 10 个请求 | `OIDC_RATE_LIMIT_EXCHANGE` |

**超出速率限制时会发生什么：**
- 端点返回 HTTP `429 Too Many Requests`
- 响应包含 `Retry-After` 头，指示何时重试
- Laravel 记录速率限制命中（检查 `storage/logs/laravel.log`）

**如何自定义速率限制：**

在 `.env` 中添加：
```env
# 格式：请求数,分钟数
OIDC_RATE_LIMIT_EXCHANGE=20,1    # 每分钟 20 个请求
OIDC_RATE_LIMIT_REDIRECT=10,1    # 每分钟 10 个请求
OIDC_RATE_LIMIT_CALLBACK=20,1    # 每分钟 20 个请求
```

**如何监控速率限制命中：**

监听 Laravel 的速率限制事件或检查日志：
```php
// 在服务提供者中
RateLimiter::hit('oidc.exchange');  // Laravel 自动记录
```

**最佳实践：**
- 根据预期的流量模式设置限制
- 监控日志以查找合法用户达到限制的情况
- 在前端重试逻辑中实现指数退避
- 对于已认证的端点，考虑按用户而不是按 IP 进行速率限制

## 7. 用户模型错误

**错误：** 回调期间出现 `MassAssignmentException` 或缺少列错误。

**原因：**
- OIDC 列（`oidc_sub`、`auth_server_refresh_token`）不在模型的 `$fillable` 数组中。
- 迁移尚未运行。
- `user_mapping.attributes` 配置引用了数据库中不存在的列。

**解决方案：**
- 将所有 OIDC 列添加到 User 模型的 `$fillable` 中。
- 运行 `php artisan migrate` 以确保迁移已应用。
- 验证 `user_mapping.attributes` 键与实际数据库列名匹配。

## 8. 缓存驱动要求

**错误：** 交换码立即过期或状态验证随机失败。

**原因：**
- 在生产环境中使用非持久化缓存驱动（例如 `array`）。
- 缓存在 OIDC 流程步骤之间被清除。
- 多个应用服务器不共享同一缓存存储。

**为什么需要持久化缓存：**

OIDC 流程在 Laravel 的缓存中存储关键数据：
1. **PKCE code_verifier** - 在重定向期间存储，在回调期间检索（生命周期：约 1-5 分钟）
2. **State 参数** - 在重定向期间存储，在回调期间验证（生命周期：约 1-5 分钟）
3. **交换码** - 在回调期间存储，在交换期间消费（生命周期：默认 5 分钟）

如果缓存不是持久化的，这些数据将丢失，流程将失败。

**使用 array 驱动会出现什么问题：**
- ✗ `array` 驱动仅将数据存储在内存中（请求之间丢失）
- ✗ 状态验证失败，出现"Invalid state"错误
- ✗ 交换码立即"过期或无效"
- ✗ 令牌交换期间 PKCE 验证失败

**推荐的缓存驱动：**

| 驱动 | 用例 | 配置 |
|--------|----------|---------------|
| `redis` | **生产环境（推荐）** | 快速、持久化、支持多服务器 |
| `database` | 生产环境 | 持久化、无额外依赖 |
| `file` | 开发环境/单服务器 | 持久化、简单设置 |
| `memcached` | 生产环境 | 快速、持久化、支持多服务器 |
| `array` | **仅测试** | 非持久化，不要在生产环境中使用 |

**如何配置缓存驱动：**

在 `.env` 中：
```env
CACHE_DRIVER=redis  # 或 database、file、memcached
```

对于 Redis：
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

对于数据库：
```bash
php artisan cache:table
php artisan migrate
```

```env
CACHE_DRIVER=database
```

**验证缓存持久化：**

测试缓存在请求之间是否持久化：
```bash
php artisan tinker
>>> cache()->put('test', 'value', 60);
>>> exit

php artisan tinker
>>> cache()->get('test');  // 应该返回 "value"
```

如果 `cache()->get('test')` 返回 `null`，你的缓存驱动不是持久化的。

**多服务器部署：**

如果运行多个应用服务器（负载均衡）：
- 使用 `redis` 或 `database` 缓存驱动（不要使用 `file`）
- 所有服务器必须连接到同一个 Redis/数据库实例
- 验证缓存是共享的：在服务器 A 上设置一个值，从服务器 B 读取它

## 调试技巧

- 监听 `OidcAuthFailed` 事件以捕获错误代码和消息，用于日志记录或监控。
- 使用 `php artisan route:list` 验证 OIDC 路由是否正确注册。
- 测试 Auth Server 连接：`curl -s https://auth.example.com/oauth/authorize | head`。

## 常见问题

### 什么是 OIDC？

OIDC（OpenID Connect）是建立在 OAuth 2.0 之上的身份验证协议。它允许应用程序验证用户身份并从外部身份提供商获取基本配置文件信息。

### 我需要外部 OIDC 提供商吗？

是的。这个包是一个**客户端**，连接到外部 OIDC 提供商（Auth Server）。你需要 Keycloak、Auth0、Okta 或自定义 OAuth2/OIDC 服务器。

### 我可以使用 `array` 缓存驱动吗？

**不可以。** `array` 驱动是非持久化的，会在请求之间丢失数据。请使用 `redis`、`database` 或 `file`。

### 为什么我的会话一直过期？

检查你的会话驱动。`array` 驱动是非持久化的。使用 `database`、`redis` 或 `file`。

### 如何实现登出？

使用 `OidcService` 创建登出控制器：

```php
public function logout(Request $request, OidcService $oidcService): JsonResponse
{
    $user = $request->user();
    $oidcService->revokeAuthServerToken($user);
    auth('api')->logout();

    $data = ['message' => '已登出'];
    if ($oidcService->isOidcUser($user)) {
        $data['logout_url'] = $oidcService->getSsoLogoutUrl();
    }

    return response()->json($data);
}
```

### 生产环境中应该使用 HTTPS 吗？

**绝对应该。** 生产环境中所有 URL 都应使用 HTTPS。

### 用户可以同时拥有 OIDC 和密码认证吗？

可以。用户可以只有 OIDC、只有密码或两者都有。

### 用户会自动创建吗？

是的。当用户首次通过 OIDC 进行身份验证时，包会自动创建用户记录。

### JWT 令牌持续多长时间？

这在你的 JWT 包（`php-open-source-saver/jwt-auth`）中配置，而不是在这个包中。检查 `config/jwt.php`。

### 我可以自定义速率限制吗？

可以，在 `.env` 中：

```env
OIDC_RATE_LIMIT_EXCHANGE=20,1
OIDC_RATE_LIMIT_REDIRECT=10,1
OIDC_RATE_LIMIT_CALLBACK=20,1
```

## 另请参阅

- [配置参考](configuration.md) - 所有配置选项和环境变量

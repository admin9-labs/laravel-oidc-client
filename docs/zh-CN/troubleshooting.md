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

**错误：** 回调后，用户被重定向，会话中闪存了 `oidc_error=auth_failed`。

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

**错误：** 回调后，用户被重定向，会话中闪存了 `oidc_error=auth_failed`。

**原因：**
- Auth Server 返回的访问令牌无效或已过期。
- 用户信息端点路径配置错误。
- Auth Server 需要额外的作用域才能访问用户信息。

**解决方案：**
- 验证配置中的 `endpoints.userinfo` 与你的 Auth Server 的用户信息端点匹配。
- 确保请求的作用域（`OIDC_SCOPES`）包含 `openid` 以及提供商要求的任何其他作用域。
- 检查 Auth Server 日志以查找令牌验证错误。

## 4. Auth Server 不可达

**错误：** 回调后，用户被重定向，会话中闪存了 `oidc_error=server_unreachable`。

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

## 5. 用户模型错误

**错误：** 回调期间出现 `MassAssignmentException` 或缺少列错误。

**原因：**
- OIDC 列（`oidc_sub`、`auth_server_refresh_token`）不在模型的 `$fillable` 数组中。
- 迁移尚未运行。
- `user_mapping.attributes` 配置引用了数据库中不存在的列。

**解决方案：**
- 将所有 OIDC 列添加到 User 模型的 `$fillable` 中。
- 运行 `php artisan migrate` 以确保迁移已应用。
- 验证 `user_mapping.attributes` 键与实际数据库列名匹配。

## 6. 会话驱动要求

**错误：** 状态验证随机失败或登录会话丢失。

**原因：**
- 在生产环境中使用非持久化会话驱动（例如 `array`）。
- 会话在 OIDC 流程步骤之间被清除。
- 多个应用服务器不共享同一会话存储。

**解决方案：**
- 使用持久化会话驱动（`redis`、`database`、`file`）— 不要使用 `array`。
- 对于多服务器部署，使用 `redis` 或 `database` 并确保所有服务器共享同一存储。

## 调试技巧

- 监听 `OidcAuthFailed` 事件以捕获错误代码和消息，用于日志记录或监控。
- 在重定向目标页面检查 `session('oidc_error')` 和 `session('oidc_error_description')` 以显示错误。
- 使用 `php artisan route:list` 验证 OIDC 路由是否正确注册。
- 测试 Auth Server 连接：`curl -s https://auth.example.com/oauth/authorize | head`。

## 常见问题

### 什么是 OIDC？

OIDC（OpenID Connect）是建立在 OAuth 2.0 之上的身份验证协议。它允许应用程序验证用户身份并从外部身份提供商获取基本配置文件信息。

### 我需要外部 OIDC 提供商吗？

是的。这个包是一个**客户端**，连接到外部 OIDC 提供商（Auth Server）。你需要 Keycloak、Auth0、Okta 或自定义 OAuth2/OIDC 服务器。

### 为什么我的会话一直过期？

检查你的会话驱动。`array` 驱动是非持久化的。使用 `database`、`redis` 或 `file`。

### 如何实现登出？

使用 `OidcService` 创建登出控制器：

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

### 生产环境中应该使用 HTTPS 吗？

**绝对应该。** 生产环境中所有 URL 都应使用 HTTPS。

### 用户可以同时拥有 OIDC 和密码认证吗？

可以。用户可以只有 OIDC、只有密码或两者都有。

### 用户会自动创建吗？

是的。当用户首次通过 OIDC 进行身份验证时，包会自动创建用户记录。

### 我可以自定义速率限制吗？

可以，在 `.env` 中：

```env
OIDC_RATE_LIMIT_REDIRECT=10,1
OIDC_RATE_LIMIT_CALLBACK=20,1
```

## 另请参阅

- [配置参考](configuration.md) - 所有配置选项和环境变量

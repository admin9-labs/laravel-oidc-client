<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auth Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your OIDC provider (Auth Server) settings here.
    |
    */

    'auth_server' => [
        'host' => env('OIDC_AUTH_SERVER_HOST', env('AUTH_SERVER_HOST')),
        'client_id' => env('OIDC_CLIENT_ID', env('AUTH_SERVER_CLIENT_ID')),
        'client_secret' => env('OIDC_CLIENT_SECRET', env('AUTH_SERVER_CLIENT_SECRET')),
        'redirect_uri' => env('OIDC_REDIRECT_URI', env('AUTH_SERVER_REDIRECT')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect URL
    |--------------------------------------------------------------------------
    |
    | Where to redirect after successful OIDC authentication.
    | Uses redirect()->intended() so any previously intended URL takes priority.
    |
    */

    'redirect_url' => env('OIDC_REDIRECT_URL', '/dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Post-Logout Redirect URL
    |--------------------------------------------------------------------------
    |
    | Where the Auth Server should redirect after SSO logout.
    |
    */

    'post_logout_redirect_url' => env('OIDC_POST_LOGOUT_REDIRECT_URL', '/'),

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    |
    | The OIDC scopes to request during authorization.
    |
    */

    'scopes' => env('OIDC_SCOPES', 'openid profile email'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class to use for users. Must have 'oidc_sub' and
    | 'auth_server_refresh_token' fields.
    |
    */

    'user_model' => env('OIDC_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | User Mapping
    |--------------------------------------------------------------------------
    |
    | Configure how OIDC userinfo claims map to your local User model.
    | - identifier_column: the DB column storing the OIDC subject ID
    | - identifier_claim: the userinfo claim used as the unique identifier
    | - refresh_token_column: the DB column storing the Auth Server refresh token
    | - attributes: map of DB column => callable or userinfo key
    |
    */

    'user_mapping' => [
        'identifier_column' => 'oidc_sub',
        'identifier_claim' => 'sub',
        'refresh_token_column' => 'auth_server_refresh_token',
        'attributes' => [
            'name' => fn ($userinfo) => $userinfo['name'] ?? $userinfo['email'],
            'email' => fn ($userinfo) => $userinfo['email'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefixes and middleware for OIDC routes.
    |
    */

    'routes' => [
        // Web routes (redirect, callback)
        'web' => [
            'prefix' => 'auth',
            'middleware' => ['web'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for OIDC endpoints.
    |
    */

    'rate_limits' => [
        'redirect' => env('OIDC_RATE_LIMIT_REDIRECT', '5,1'), // 5 requests per minute
        'callback' => env('OIDC_RATE_LIMIT_CALLBACK', '10,1'), // 10 requests per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeouts
    |--------------------------------------------------------------------------
    |
    | Configure HTTP request timeouts for Auth Server communication.
    |
    */

    'http' => [
        'timeout' => env('OIDC_HTTP_TIMEOUT', 15),
        'retry_times' => env('OIDC_HTTP_RETRY_TIMES', 2),
        'retry_delay' => env('OIDC_HTTP_RETRY_DELAY', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth Server Endpoints
    |--------------------------------------------------------------------------
    |
    | Configure the Auth Server endpoint paths. These are appended to the
    | auth_server.host URL.
    |
    */

    'endpoints' => [
        'authorize' => '/oauth/authorize',
        'token' => '/oauth/token',
        'userinfo' => '/api/oauth/userinfo',
        'revoke' => '/oauth/revoke',
        'logout' => '/oauth/logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use for web session login during callback.
    |
    */

    'web_guard' => env('OIDC_WEB_GUARD', 'web'),
];

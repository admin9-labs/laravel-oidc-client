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
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | The URL of your frontend application. Used for redirects after
    | authentication and logout.
    |
    */

    'frontend_url' => env('OIDC_FRONTEND_URL', env('FRONTEND_URL', 'http://localhost:3000')),

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
    | Exchange Code TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) the one-time exchange code is valid.
    |
    */

    'exchange_code_ttl' => env('OIDC_EXCHANGE_CODE_TTL', 5),

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

        // API routes (exchange, logout)
        'api' => [
            'prefix' => 'api/auth',
            'middleware' => ['api'],
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
        'exchange' => env('OIDC_RATE_LIMIT_EXCHANGE', '10,1'), // 10 requests per minute
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
    | JWT Guard
    |--------------------------------------------------------------------------
    |
    | The authentication guard to use for JWT operations.
    |
    */

    'jwt_guard' => env('OIDC_JWT_GUARD', 'api'),
];

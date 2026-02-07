<?php

use Admin9\OidcClient\Exceptions\OidcException;
use Admin9\OidcClient\Exceptions\OidcServerException;
use Admin9\OidcClient\Services\OidcService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(OidcService::class);
});

describe('exchangeCodeForTokens', function () {
    it('exchanges code for tokens successfully', function () {
        Http::fake([
            'auth.example.com/oauth/token' => Http::response([
                'access_token' => 'test-access-token',
                'refresh_token' => 'test-refresh-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);

        $tokens = $this->service->exchangeCodeForTokens('test-code', 'test-verifier');

        expect($tokens)
            ->toHaveKey('access_token', 'test-access-token')
            ->toHaveKey('refresh_token', 'test-refresh-token');
    });

    it('throws exception when token exchange fails', function () {
        Http::fake([
            'auth.example.com/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'The authorization code has expired',
            ], 400),
        ]);

        expect(fn () => $this->service->exchangeCodeForTokens('expired-code', 'test-verifier'))
            ->toThrow(OidcServerException::class);
    });

    it('throws exception when access_token is missing', function () {
        Http::fake([
            'auth.example.com/oauth/token' => Http::response([
                'token_type' => 'Bearer',
            ]),
        ]);

        expect(fn () => $this->service->exchangeCodeForTokens('test-code', 'test-verifier'))
            ->toThrow(OidcException::class, 'Token response missing access_token');
    });
});

describe('fetchUserInfo', function () {
    it('fetches user info successfully', function () {
        Http::fake([
            'auth.example.com/api/oauth/userinfo' => Http::response([
                'sub' => 'user-123',
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]),
        ]);

        $userInfo = $this->service->fetchUserInfo('test-access-token');

        expect($userInfo)
            ->toHaveKey('sub', 'user-123')
            ->toHaveKey('name', 'Test User')
            ->toHaveKey('email', 'test@example.com');
    });

    it('throws exception when userinfo request fails', function () {
        Http::fake([
            'auth.example.com/api/oauth/userinfo' => Http::response('Unauthorized', 401),
        ]);

        expect(fn () => $this->service->fetchUserInfo('invalid-token'))
            ->toThrow(OidcServerException::class);
    });

    it('throws exception when identifier claim is missing', function () {
        Http::fake([
            'auth.example.com/api/oauth/userinfo' => Http::response([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]),
        ]);

        expect(fn () => $this->service->fetchUserInfo('test-access-token'))
            ->toThrow(OidcException::class, 'missing required claim');
    });
});

describe('getSsoLogoutUrl', function () {
    it('generates correct SSO logout URL', function () {
        $url = $this->service->getSsoLogoutUrl();

        expect($url)
            ->toContain('https://auth.example.com/oauth/logout')
            ->toContain('post_logout_redirect_uri=');
    });
});

describe('isOidcUser', function () {
    it('returns true for user with oidc_sub', function () {
        $user = new Admin9\OidcClient\Tests\Fixtures\User;
        $user->oidc_sub = 'user-123';

        expect($this->service->isOidcUser($user))->toBeTrue();
    });

    it('returns false for user without oidc_sub', function () {
        $user = new Admin9\OidcClient\Tests\Fixtures\User;
        $user->oidc_sub = null;

        expect($this->service->isOidcUser($user))->toBeFalse();
    });

    it('returns false for null user', function () {
        expect($this->service->isOidcUser(null))->toBeFalse();
    });
});

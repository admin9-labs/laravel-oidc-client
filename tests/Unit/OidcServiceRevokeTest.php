<?php

use Admin9\OidcClient\Services\OidcService;
use Admin9\OidcClient\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(OidcService::class);
});

describe('revokeAuthServerToken', function () {
    it('returns true when user has no refresh token', function () {
        $user = new User;
        $user->auth_server_refresh_token = null;

        expect($this->service->revokeAuthServerToken($user))->toBeTrue();
    });

    it('returns true for null user', function () {
        expect($this->service->revokeAuthServerToken(null))->toBeTrue();
    });

    it('returns false when revocation endpoint returns error', function () {
        Http::fake([
            'auth.example.com/oauth/revoke' => Http::response('Server Error', 500),
        ]);

        $user = new User;
        $user->id = 1;
        $user->auth_server_refresh_token = 'some-token';

        expect($this->service->revokeAuthServerToken($user))->toBeFalse();
    });
});

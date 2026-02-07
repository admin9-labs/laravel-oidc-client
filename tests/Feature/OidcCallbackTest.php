<?php

use Admin9\OidcClient\Events\OidcAuthFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

describe('callback', function () {
    it('rejects callback without state in session', function () {
        $this->get('/auth/callback?code=test-code&state=some-state')
            ->assertStatus(403);
    });

    it('rejects callback with mismatched state', function () {
        Session::put('oidc_state', 'correct-state');
        Session::put('oidc_code_verifier', 'test-verifier');

        $this->get('/auth/callback?code=test-code&state=wrong-state')
            ->assertStatus(403);
    });

    it('dispatches OidcAuthFailed event on authorization denial', function () {
        Event::fake([OidcAuthFailed::class]);

        $this->get('/auth/callback?error=access_denied&error_description=User+denied');

        Event::assertDispatched(OidcAuthFailed::class, function ($event) {
            return $event->errorCode === 'access_denied';
        });
    });

    it('sanitizes unknown error codes from auth server', function () {
        Event::fake([OidcAuthFailed::class]);

        $this->get('/auth/callback?error=custom_evil_error&error_description=test');

        Event::assertDispatched(OidcAuthFailed::class, function ($event) {
            return $event->errorCode === 'unknown_error';
        });
    });

    it('handles server_error from auth server', function () {
        $response = $this->get('/auth/callback?error=server_error&error_description=Internal+error');

        $response->assertRedirect();
        $response->assertRedirectContains('error=server_error');
    });

    it('redirects to frontend with error on token exchange failure', function () {
        Session::put('oidc_state', 'valid-state');
        Session::put('oidc_code_verifier', 'test-verifier');

        Http::fake([
            'auth.example.com/oauth/token' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $response = $this->get('/auth/callback?code=test-code&state=valid-state');

        $response->assertRedirect();
        $response->assertRedirectContains('error=auth_failed');
    });
});

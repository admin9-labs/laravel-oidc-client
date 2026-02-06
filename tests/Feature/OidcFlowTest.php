<?php

use Illuminate\Support\Str;

it('generates valid PKCE and state on redirect', function () {
    $response = $this->get('/auth/redirect');

    $response->assertRedirect();
    $response->assertRedirectContains('oauth/authorize');
    $response->assertRedirectContains('code_challenge=');
    $response->assertRedirectContains('code_challenge_method=S256');
    $response->assertRedirectContains('state=');

    expect(session('oidc_state'))->not->toBeNull()->toHaveLength(40);
    expect(session('oidc_code_verifier'))->not->toBeNull()->toHaveLength(128);
});

it('validates state parameter on callback', function () {
    $this->get('/auth/callback?code=test&state=invalid')
        ->assertStatus(403);
});

it('handles authorization denial on callback', function () {
    $response = $this->get('/auth/callback?error=access_denied&error_description=User+denied+access');

    $frontendUrl = config('oidc-client.frontend_url');
    $response->assertRedirect();
    $response->assertRedirectContains($frontendUrl.'/auth/callback');
    $response->assertRedirectContains('error=access_denied');
});

it('validates code format on exchange', function () {
    $this->postJson('/api/auth/exchange', [
        'code' => 'not-a-valid-uuid',
    ])->assertUnprocessable();
});

it('rejects invalid exchange code', function () {
    $this->postJson('/api/auth/exchange', [
        'code' => Str::uuid()->toString(),
    ])->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'Invalid or expired exchange code',
        ]);
});

it('loads configuration correctly', function () {
    expect(config('oidc-client.auth_server.host'))->toBe('https://auth.example.com');
    expect(config('oidc-client.auth_server.client_id'))->toBe('test-client-id');
    expect(config('oidc-client.auth_server.client_secret'))->toBe('test-client-secret');
    expect(config('oidc-client.auth_server.redirect_uri'))->toBe('http://localhost/auth/callback');
    expect(config('oidc-client.frontend_url'))->toBe('http://localhost:3000');
});

it('registers routes correctly', function () {
    expect(\Route::has('oidc.redirect'))->toBeTrue();
    expect(\Route::has('oidc.callback'))->toBeTrue();
    expect(\Route::has('auth.exchange'))->toBeTrue();
});

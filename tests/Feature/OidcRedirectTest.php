<?php

describe('redirect', function () {
    it('redirects to auth server with correct parameters', function () {
        $response = $this->get('/auth/redirect');

        $response->assertRedirect();
        $response->assertRedirectContains('https://auth.example.com/oauth/authorize');
        $response->assertRedirectContains('client_id=test-client-id');
        $response->assertRedirectContains('response_type=code');
        $response->assertRedirectContains('code_challenge=');
        $response->assertRedirectContains('code_challenge_method=S256');
        $response->assertRedirectContains('state=');
    });

    it('stores state and code_verifier in session', function () {
        $this->get('/auth/redirect');

        expect(session('oidc_state'))
            ->not->toBeNull()
            ->toHaveLength(40);

        expect(session('oidc_code_verifier'))
            ->not->toBeNull()
            ->toHaveLength(128);
    });

    it('generates unique state for each request', function () {
        $this->get('/auth/redirect');
        $state1 = session('oidc_state');

        $this->get('/auth/redirect');
        $state2 = session('oidc_state');

        expect($state1)->not->toBe($state2);
    });

    it('includes configured scopes', function () {
        $response = $this->get('/auth/redirect');

        $response->assertRedirectContains('scope=');
    });
});

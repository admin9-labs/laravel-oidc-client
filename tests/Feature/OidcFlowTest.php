<?php

namespace Admin9\OidcClient\Tests\Feature;

use Admin9\OidcClient\Tests\TestCase;
use Illuminate\Support\Str;

class OidcFlowTest extends TestCase
{
    /**
     * Test redirect endpoint generates valid PKCE and state parameters.
     */
    public function test_redirect_generates_valid_pkce_and_state(): void
    {
        $response = $this->get('/auth/redirect');

        $response->assertRedirect();
        $response->assertRedirectContains('oauth/authorize');
        $response->assertRedirectContains('code_challenge=');
        $response->assertRedirectContains('code_challenge_method=S256');
        $response->assertRedirectContains('state=');

        // Verify session stores necessary values
        $this->assertNotNull(session('oidc_state'));
        $this->assertNotNull(session('oidc_code_verifier'));
        $this->assertEquals(40, strlen(session('oidc_state')));
        $this->assertEquals(128, strlen(session('oidc_code_verifier')));
    }

    /**
     * Test callback validates state parameter to prevent CSRF.
     */
    public function test_callback_validates_state_parameter(): void
    {
        // Request without session state should fail
        $response = $this->get('/auth/callback?code=test&state=invalid');

        $response->assertStatus(403);
    }

    /**
     * Test callback handles authorization denial.
     */
    public function test_callback_handles_authorization_denial(): void
    {
        $response = $this->get('/auth/callback?error=access_denied&error_description=User+denied+access');

        $frontendUrl = config('oidc.frontend_url');
        $response->assertRedirect();
        $response->assertRedirectContains($frontendUrl.'/auth/callback');
        $response->assertRedirectContains('error=access_denied');
    }

    /**
     * Test token exchange validates code format.
     */
    public function test_token_exchange_validates_code_format(): void
    {
        $response = $this->postJson('/api/auth/exchange', [
            'code' => 'not-a-valid-uuid',
        ]);

        $response->assertUnprocessable();
    }

    /**
     * Test token exchange rejects invalid code.
     */
    public function test_token_exchange_rejects_invalid_code(): void
    {
        $response = $this->postJson('/api/auth/exchange', [
            'code' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or expired exchange code',
        ]);
    }

    /**
     * Test configuration is loaded correctly.
     */
    public function test_configuration_is_loaded(): void
    {
        $this->assertEquals('https://auth.example.com', config('oidc.auth_server.host'));
        $this->assertEquals('test-client-id', config('oidc.auth_server.client_id'));
        $this->assertEquals('test-client-secret', config('oidc.auth_server.client_secret'));
        $this->assertEquals('http://localhost/auth/callback', config('oidc.auth_server.redirect_uri'));
        $this->assertEquals('http://localhost:3000', config('oidc.frontend_url'));
    }

    /**
     * Test routes are registered correctly.
     */
    public function test_routes_are_registered(): void
    {
        $this->assertTrue(\Route::has('oidc.redirect'));
        $this->assertTrue(\Route::has('oidc.callback'));
        $this->assertTrue(\Route::has('auth.exchange'));
    }
}

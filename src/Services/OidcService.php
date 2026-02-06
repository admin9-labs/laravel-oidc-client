<?php

namespace Admin9\OidcClient\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OidcService
{
    /**
     * Revoke Auth Server token for a user.
     * Call this during logout to ensure complete session termination.
     *
     * @param  mixed  $user  User model with auth_server_refresh_token field
     * @return bool Whether revocation was successful
     */
    public function revokeAuthServerToken($user): bool
    {
        if (! $user || ! $user->auth_server_refresh_token) {
            return true; // Nothing to revoke
        }

        try {
            $httpConfig = config('oidc.http');
            $revokeUrl = config('oidc.auth_server.host').config('oidc.endpoints.revoke');

            $response = Http::timeout($httpConfig['timeout'] ?? 10)
                ->withBasicAuth(
                    config('oidc.auth_server.client_id'),
                    config('oidc.auth_server.client_secret')
                )
                ->acceptJson()
                ->asForm()
                ->post($revokeUrl, [
                    'token' => $user->auth_server_refresh_token,
                    'token_type_hint' => 'refresh_token',
                ]);

            if ($response->successful()) {
                Log::info('OIDC: Auth Server token revoked', ['user_id' => $user->id]);
                $user->update(['auth_server_refresh_token' => null]);

                return true;
            }

            Log::warning('OIDC: Failed to revoke Auth Server token', [
                'user_id' => $user->id,
                'status' => $response->status(),
            ]);

            return false;

        } catch (ConnectionException $e) {
            Log::warning('OIDC: Could not reach Auth Server for token revocation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the SSO logout URL for frontend redirect.
     */
    public function getSsoLogoutUrl(): string
    {
        return config('oidc.auth_server.host').config('oidc.endpoints.logout').'?'.http_build_query([
            'post_logout_redirect_uri' => config('oidc.frontend_url'),
        ]);
    }

    /**
     * Check if user authenticated via OIDC.
     */
    public function isOidcUser($user): bool
    {
        return $user && ! empty($user->oidc_sub);
    }
}

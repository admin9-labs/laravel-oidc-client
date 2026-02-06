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
        $refreshTokenColumn = config('oidc-client.user_mapping.refresh_token_column', 'auth_server_refresh_token');

        if (! $user || ! $user->{$refreshTokenColumn}) {
            return true; // Nothing to revoke
        }

        try {
            $httpConfig = config('oidc-client.http');
            $revokeUrl = config('oidc-client.auth_server.host').config('oidc-client.endpoints.revoke');

            $response = Http::timeout($httpConfig['timeout'] ?? 10)
                ->withBasicAuth(
                    config('oidc-client.auth_server.client_id'),
                    config('oidc-client.auth_server.client_secret')
                )
                ->acceptJson()
                ->asForm()
                ->post($revokeUrl, [
                    'token' => $user->{$refreshTokenColumn},
                    'token_type_hint' => 'refresh_token',
                ]);

            if ($response->successful()) {
                Log::info('OIDC: Auth Server token revoked', ['user_id' => $user->id]);
                $user->update([$refreshTokenColumn => null]);

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
        return config('oidc-client.auth_server.host').config('oidc-client.endpoints.logout').'?'.http_build_query([
            'post_logout_redirect_uri' => config('oidc-client.frontend_url'),
        ]);
    }

    /**
     * Check if user authenticated via OIDC.
     */
    public function isOidcUser($user): bool
    {
        $identifierColumn = config('oidc-client.user_mapping.identifier_column', 'oidc_sub');

        return $user && ! empty($user->{$identifierColumn});
    }
}

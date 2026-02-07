<?php

namespace Admin9\OidcClient\Http\Controllers;

use Admin9\OidcClient\Events\OidcAuthFailed;
use Admin9\OidcClient\Events\OidcTokenExchanged;
use Admin9\OidcClient\Events\OidcUserAuthenticated;
use Admin9\OidcClient\Services\OidcService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class OidcController extends Controller
{
    /**
     * Initiate OIDC authorization request with PKCE.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $codeVerifier = Str::random(128);
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        Session::put('oidc_state', $state);
        Session::put('oidc_code_verifier', $codeVerifier);

        $query = http_build_query([
            'client_id' => config('oidc-client.auth_server.client_id'),
            'redirect_uri' => config('oidc-client.auth_server.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('oidc-client.scopes'),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        Log::info('OIDC: Initiating authorization redirect', [
            'client_id' => config('oidc-client.auth_server.client_id'),
        ]);

        $authorizeUrl = config('oidc-client.auth_server.host').config('oidc-client.endpoints.authorize');

        return redirect($authorizeUrl.'?'.$query);
    }

    /**
     * Handle Auth Server callback.
     *
     * 1. Validate state parameter to prevent CSRF
     * 2. Check if authorization was denied
     * 3. Exchange authorization code for tokens
     * 4. Fetch user info and create/update local user
     * 5. Store encrypted refresh_token for later use
     */
    public function callback(Request $request): RedirectResponse
    {
        // Check if user denied authorization
        if ($request->has('error')) {
            Log::warning('OIDC: Authorization denied by user', [
                'error' => $request->error,
                'description' => $request->error_description,
            ]);

            OidcAuthFailed::dispatch($request->error, $request->error_description ?? 'Authorization denied');

            return $this->redirectToFrontendWithError(
                $request->error,
                $request->error_description ?? __('oidc-client::messages.authorization_denied')
            );
        }

        $state = Session::pull('oidc_state');
        $codeVerifier = Session::pull('oidc_code_verifier');

        if (! $state || $state !== $request->state) {
            Log::warning('OIDC: Invalid state parameter', [
                'expected' => $state,
                'received' => $request->state,
            ]);
            abort(403, 'Invalid state');
        }

        try {
            $oidcService = app(OidcService::class);

            $tokens = $oidcService->exchangeCodeForTokens($request->code, $codeVerifier);
            $accessToken = $tokens['access_token'];
            $refreshToken = $tokens['refresh_token'] ?? null;

            $userData = $oidcService->fetchUserInfo($accessToken);

            $userModel = config('oidc-client.user_model');
            $mapping = config('oidc-client.user_mapping');
            $identifierColumn = $mapping['identifier_column'] ?? 'oidc_sub';
            $identifierClaim = $mapping['identifier_claim'] ?? 'sub';
            $refreshTokenColumn = $mapping['refresh_token_column'] ?? 'auth_server_refresh_token';

            $attributes = [];
            foreach ($mapping['attributes'] ?? [] as $column => $resolver) {
                $attributes[$column] = is_callable($resolver)
                    ? $resolver($userData)
                    : ($userData[$resolver] ?? null);
            }

            $user = $userModel::updateOrCreate(
                [$identifierColumn => $userData[$identifierClaim]],
                $attributes
            );

            // Store Auth Server refresh_token for silent refresh and secure logout
            // Model's encrypted cast will automatically encrypt
            if ($refreshToken) {
                $user->update([
                    $refreshTokenColumn => $refreshToken,
                ]);
            }

            Log::info('OIDC: User authenticated', [
                'user_id' => $user->id,
                $identifierColumn => $user->{$identifierColumn},
                'is_new' => $user->wasRecentlyCreated,
                'has_refresh_token' => (bool) $refreshToken,
            ]);

            OidcUserAuthenticated::dispatch($user, $userData, $user->wasRecentlyCreated);

            // Login user and regenerate session
            Auth::guard(config('oidc-client.web_guard', 'web'))->login($user);
            $request->session()->regenerate();

            $exchangeCode = Str::uuid()->toString();
            $ttl = config('oidc-client.exchange_code_ttl');
            Cache::put('oidc_exchange_'.$exchangeCode, $user->id, now()->addMinutes($ttl));

            $frontendUrl = config('oidc-client.frontend_url');

            return redirect($frontendUrl.'/auth/callback?code='.$exchangeCode);

        } catch (\RuntimeException $e) {
            Log::error('OIDC: '.$e->getMessage());

            OidcAuthFailed::dispatch('auth_failed', $e->getMessage());

            return $this->redirectToFrontendWithError('auth_failed', $e->getMessage());
        } catch (ConnectionException $e) {
            Log::critical('OIDC: Auth Server unreachable', [
                'error' => $e->getMessage(),
                'host' => config('oidc-client.auth_server.host'),
            ]);

            OidcAuthFailed::dispatch('server_unreachable', $e->getMessage());

            return $this->redirectToFrontendWithError('server_unreachable', __('oidc-client::messages.server_unreachable'));
        }
    }

    /**
     * Exchange code for token.
     *
     * Exchange one-time OIDC code for JWT token. This endpoint is rate-limited.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => 'required|uuid']);
        $userId = Cache::pull('oidc_exchange_'.$validated['code']);

        if (! $userId) {
            Log::warning('OIDC: Invalid or expired exchange code attempted');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired exchange code',
            ], 401);
        }

        $userModel = config('oidc-client.user_model');
        $user = $userModel::findOrFail($userId);
        $guard = config('oidc-client.jwt_guard');
        $token = auth($guard)->login($user);

        Log::info('OIDC: Token exchanged successfully', [
            'user_id' => $user->id,
        ]);

        OidcTokenExchanged::dispatch($user);

        return response()->json([
            'success' => true,
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    /**
     * Redirect to frontend with error information.
     */
    private function redirectToFrontendWithError(string $code, string $message): RedirectResponse
    {
        $frontendUrl = config('oidc-client.frontend_url');
        $params = http_build_query([
            'error' => $code,
            'error_description' => $message,
        ]);

        return redirect($frontendUrl.'/auth/callback?'.$params);
    }
}

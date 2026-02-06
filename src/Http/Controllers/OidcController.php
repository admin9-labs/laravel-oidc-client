<?php

namespace Admin9\OidcClient\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            'client_id' => config('oidc.auth_server.client_id'),
            'redirect_uri' => config('oidc.auth_server.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('oidc.scopes'),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        Log::info('OIDC: Initiating authorization redirect', [
            'client_id' => config('oidc.auth_server.client_id'),
        ]);

        $authorizeUrl = config('oidc.auth_server.host').config('oidc.endpoints.authorize');

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

            return $this->redirectToFrontendWithError(
                $request->error,
                $request->error_description ?? __('auth.authorization_denied')
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
            $httpConfig = config('oidc.http');
            $tokenUrl = config('oidc.auth_server.host').config('oidc.endpoints.token');

            $response = Http::timeout($httpConfig['timeout'])
                ->retry($httpConfig['retry_times'], $httpConfig['retry_delay'])
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('oidc.auth_server.client_id'),
                    'client_secret' => config('oidc.auth_server.client_secret'),
                    'redirect_uri' => config('oidc.auth_server.redirect_uri'),
                    'code' => $request->code,
                    'code_verifier' => $codeVerifier,
                ]);

            if ($response->failed()) {
                Log::error('OIDC: Token exchange failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                    'error_description' => $response->json('error_description'),
                ]);

                return $this->redirectToFrontendWithError('token_exchange_failed', __('auth.token_exchange_failed'));
            }

            $tokens = $response->json();
            $accessToken = $tokens['access_token'];
            $refreshToken = $tokens['refresh_token'] ?? null;

            $userinfoUrl = config('oidc.auth_server.host').config('oidc.endpoints.userinfo');

            $userResponse = Http::timeout($httpConfig['timeout'] - 5)
                ->retry($httpConfig['retry_times'], $httpConfig['retry_delay'])
                ->withToken($accessToken)
                ->get($userinfoUrl);

            if ($userResponse->failed()) {
                Log::error('OIDC: Failed to fetch user info', [
                    'status' => $userResponse->status(),
                ]);

                return $this->redirectToFrontendWithError('userinfo_failed', __('auth.userinfo_failed'));
            }

            $userData = $userResponse->json();

            $userModel = config('oidc.user_model');
            $user = $userModel::updateOrCreate(
                ['oidc_sub' => $userData['sub']],
                [
                    'name' => $userData['name'] ?? $userData['email'],
                    'email' => $userData['email'],
                ]
            );

            // Store Auth Server refresh_token for silent refresh and secure logout
            // Model's encrypted cast will automatically encrypt
            if ($refreshToken) {
                $user->update([
                    'auth_server_refresh_token' => $refreshToken,
                ]);
            }

            Log::info('OIDC: User authenticated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'oidc_sub' => $user->oidc_sub,
                'is_new' => $user->wasRecentlyCreated,
                'has_refresh_token' => (bool) $refreshToken,
            ]);

            // Login user and regenerate session
            Auth::login($user);
            $request->session()->regenerate();

            $exchangeCode = Str::uuid()->toString();
            $ttl = config('oidc.exchange_code_ttl');
            Cache::put('oidc_exchange_'.$exchangeCode, $user->id, now()->addMinutes($ttl));

            $frontendUrl = config('oidc.frontend_url');

            return redirect($frontendUrl.'/auth/callback?code='.$exchangeCode);

        } catch (ConnectionException $e) {
            Log::critical('OIDC: Auth Server unreachable', [
                'error' => $e->getMessage(),
                'host' => config('oidc.auth_server.host'),
            ]);

            return $this->redirectToFrontendWithError('server_unreachable', __('auth.server_unreachable'));
        }
    }

    /**
     * Exchange one-time code for JWT token.
     * This endpoint is rate-limited.
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

        $userModel = config('oidc.user_model');
        $user = $userModel::findOrFail($userId);
        $guard = config('oidc.jwt_guard');
        $token = auth($guard)->login($user);

        Log::info('OIDC: Token exchanged successfully', [
            'user_id' => $user->id,
        ]);

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
        $frontendUrl = config('oidc.frontend_url');
        $params = http_build_query([
            'error' => $code,
            'error_description' => $message,
        ]);

        return redirect($frontendUrl.'/auth/callback?'.$params);
    }
}

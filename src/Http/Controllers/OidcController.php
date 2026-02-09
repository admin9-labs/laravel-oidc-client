<?php

namespace Admin9\OidcClient\Http\Controllers;

use Admin9\OidcClient\Events\OidcAuthFailed;
use Admin9\OidcClient\Events\OidcUserAuthenticated;
use Admin9\OidcClient\Exceptions\OidcException;
use Admin9\OidcClient\Services\OidcService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class OidcController extends Controller
{
    private const ALLOWED_ERROR_CODES = [
        'access_denied', 'invalid_request', 'unauthorized_client',
        'unsupported_response_type', 'invalid_scope', 'server_error',
        'temporarily_unavailable',
    ];

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

        $authorizeUrl = config('oidc-client.auth_server.host').config('oidc-client.endpoints.authorize');

        return redirect($authorizeUrl.'?'.$query);
    }

    /**
     * Handle Auth Server callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $errorCode = in_array($request->error, self::ALLOWED_ERROR_CODES, true)
                ? $request->error
                : 'unknown_error';

            OidcAuthFailed::dispatch($errorCode, $request->error_description ?? 'Authorization denied');

            return $this->redirectWithError(
                $errorCode,
                $request->error_description ?? __('oidc-client::messages.authorization_denied')
            );
        }

        $state = Session::pull('oidc_state');
        $codeVerifier = Session::pull('oidc_code_verifier');

        if (! $state || $state !== $request->state) {
            abort(403, 'Invalid state');
        }

        try {
            $oidcService = app(OidcService::class);

            $tokens = $oidcService->exchangeCodeForTokens($request->code, $codeVerifier);
            $userData = $oidcService->fetchUserInfo($tokens['access_token']);
            $user = $oidcService->findOrCreateUser($userData, $tokens['refresh_token'] ?? null);

            OidcUserAuthenticated::dispatch($user, $userData, $user->wasRecentlyCreated);

            Auth::guard(config('oidc-client.web_guard', 'web'))->login($user);
            $request->session()->regenerate();

            return redirect()->intended(config('oidc-client.redirect_url', '/dashboard'));

        } catch (OidcException $e) {
            OidcAuthFailed::dispatch('auth_failed', $e->getMessage());

            return $this->redirectWithError('auth_failed', __('oidc-client::messages.token_exchange_failed'));
        } catch (ConnectionException $e) {
            OidcAuthFailed::dispatch('server_unreachable', $e->getMessage());

            return $this->redirectWithError('server_unreachable', __('oidc-client::messages.server_unreachable'));
        }
    }

    private function redirectWithError(string $code, string $message): RedirectResponse
    {
        return redirect(config('oidc-client.redirect_url', '/dashboard'))
            ->with('oidc_error', $code)
            ->with('oidc_error_description', $message);
    }
}

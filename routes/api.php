<?php

use Admin9\OidcClient\Http\Controllers\OidcController;
use Illuminate\Support\Facades\Route;

$apiConfig = config('oidc-client.routes.api', [
    'prefix' => 'api/auth',
    'middleware' => ['api'],
]);

$rateLimits = config('oidc-client.rate_limits', [
    'exchange' => '10,1',
]);

Route::prefix($apiConfig['prefix'])
    ->middleware($apiConfig['middleware'])
    ->group(function () use ($rateLimits) {
        Route::post('/exchange', [OidcController::class, 'exchange'])
            ->middleware('throttle:'.$rateLimits['exchange'])
            ->name('auth.exchange');
    });

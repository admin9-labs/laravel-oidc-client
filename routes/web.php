<?php

use Admin9\OidcClient\Http\Controllers\OidcController;
use Illuminate\Support\Facades\Route;

$webConfig = config('oidc-client.routes.web', [
    'prefix' => 'auth',
    'middleware' => ['web'],
]);

$redirectLimit = config('oidc-client.rate_limits.redirect', '5,1');
$callbackLimit = config('oidc-client.rate_limits.callback', '10,1');

Route::prefix($webConfig['prefix'])
    ->middleware($webConfig['middleware'])
    ->group(function () use ($redirectLimit, $callbackLimit) {
        Route::get('/redirect', [OidcController::class, 'redirect'])
            ->middleware("throttle:{$redirectLimit}")
            ->name('oidc.redirect');
        Route::get('/callback', [OidcController::class, 'callback'])
            ->middleware("throttle:{$callbackLimit}")
            ->name('oidc.callback');
    });

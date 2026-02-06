<?php

use Admin9\OidcClient\Http\Controllers\OidcController;
use Illuminate\Support\Facades\Route;

$webConfig = config('oidc-client.routes.web', [
    'prefix' => 'auth',
    'middleware' => ['web'],
]);

Route::prefix($webConfig['prefix'])
    ->middleware($webConfig['middleware'])
    ->group(function () {
        Route::get('/redirect', [OidcController::class, 'redirect'])->name('oidc.redirect');
        Route::get('/callback', [OidcController::class, 'callback'])->name('oidc.callback');
    });

<?php

use Admin9\OidcClient\Events\OidcAuthFailed;
use Admin9\OidcClient\Events\OidcTokenExchanged;
use Admin9\OidcClient\Events\OidcUserAuthenticated;
use Admin9\OidcClient\Tests\Fixtures\User;

describe('OidcAuthFailed', function () {
    it('stores error code and message', function () {
        $event = new OidcAuthFailed('access_denied', 'User denied access');

        expect($event->errorCode)->toBe('access_denied');
        expect($event->errorMessage)->toBe('User denied access');
    });
});

describe('OidcUserAuthenticated', function () {
    it('stores user, userInfo and isNewUser flag', function () {
        $user = new User;
        $userInfo = ['sub' => '123', 'email' => 'test@example.com'];

        $event = new OidcUserAuthenticated($user, $userInfo, true);

        expect($event->user)->toBe($user);
        expect($event->userInfo)->toBe($userInfo);
        expect($event->isNewUser)->toBeTrue();
    });
});

describe('OidcTokenExchanged', function () {
    it('stores user', function () {
        $user = new User;

        $event = new OidcTokenExchanged($user);

        expect($event->user)->toBe($user);
    });
});

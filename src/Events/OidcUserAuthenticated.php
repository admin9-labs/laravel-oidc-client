<?php

namespace Admin9\OidcClient\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OidcUserAuthenticated
{
    use Dispatchable;

    public function __construct(
        public readonly mixed $user,
        public readonly array $userInfo,
        public readonly bool $isNewUser,
    ) {}
}

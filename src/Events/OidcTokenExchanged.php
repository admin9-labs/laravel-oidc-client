<?php

namespace Admin9\OidcClient\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OidcTokenExchanged
{
    use Dispatchable;

    public function __construct(
        public readonly mixed $user,
    ) {}
}

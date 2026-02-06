<?php

namespace Admin9\OidcClient\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OidcAuthFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $errorCode,
        public readonly string $errorMessage,
    ) {}
}

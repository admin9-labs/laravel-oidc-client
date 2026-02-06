<?php

namespace Admin9\OidcClient\Tests;

use Admin9\OidcClient\OidcServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            OidcServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('oidc-client.auth_server.host', 'https://auth.example.com');
        $app['config']->set('oidc-client.auth_server.client_id', 'test-client-id');
        $app['config']->set('oidc-client.auth_server.client_secret', 'test-client-secret');
        $app['config']->set('oidc-client.auth_server.redirect_uri', 'http://localhost/auth/callback');
        $app['config']->set('oidc-client.frontend_url', 'http://localhost:3000');
        $app['config']->set('oidc-client.user_model', 'Admin9\\OidcClient\\Tests\\Fixtures\\User');
    }
}

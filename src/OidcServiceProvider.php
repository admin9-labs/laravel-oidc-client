<?php

namespace Admin9\OidcClient;

use Admin9\OidcClient\Services\OidcService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OidcServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('oidc-client')
            ->hasConfigFile('oidc-client')
            ->hasTranslations()
            ->hasMigration('2024_01_01_000000_add_oidc_fields_to_users_table')
            ->hasRoute('web');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OidcService::class);
    }
}

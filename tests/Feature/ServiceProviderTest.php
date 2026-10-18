<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SocialDept\AtpAppviewFilament\AtpAppviewFilamentServiceProvider;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AtpAppviewFilamentServiceProvider::class];
    }

    public function test_the_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(AtpAppviewFilamentServiceProvider::class));
    }
}

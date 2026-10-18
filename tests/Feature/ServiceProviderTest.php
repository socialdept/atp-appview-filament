<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use SocialDept\AtpAppviewFilament\AtpAppviewFilamentServiceProvider;
use SocialDept\AtpAppviewFilament\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(AtpAppviewFilamentServiceProvider::class));
    }
}

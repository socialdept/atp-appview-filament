<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Tests\Fixtures\AtpUser;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpClient\Contracts\CredentialProvider;

class LogoutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AtpUser::class);
    }

    public function test_backend_mode_forgets_the_server_held_session(): void
    {
        config()->set('atp-appview.auth.mode', 'backend');

        $credentials = Mockery::mock(CredentialProvider::class);
        $credentials->shouldReceive('removeCredentials')->once()->with('did:plc:alice');
        $this->app->instance(CredentialProvider::class, $credentials);

        $this->logout()->assertRedirect(Filament::getUrl());

        $this->assertGuest();
    }

    public function test_frontend_mode_sends_the_browser_to_sign_itself_out(): void
    {
        config()->set('atp-appview.auth.mode', 'frontend');
        AtpAppviewPlugin::get()->signOutUrl(fn () => url('/sign-out'));

        $credentials = Mockery::mock(CredentialProvider::class);
        $credentials->shouldNotReceive('removeCredentials');
        $this->app->instance(CredentialProvider::class, $credentials);

        $this->logout()->assertRedirect(url('/sign-out'));

        $this->assertGuest();
    }

    public function test_frontend_mode_without_a_sign_out_page_logs_out_as_filament_does(): void
    {
        config()->set('atp-appview.auth.mode', 'frontend');

        $this->logout()->assertRedirect(Filament::getUrl());

        $this->assertGuest();
    }

    public function test_sign_out_can_be_turned_off(): void
    {
        config()->set('atp-appview.auth.mode', 'backend');
        AtpAppviewPlugin::get()->signOut(false)->signOutUrl('/sign-out');

        $credentials = Mockery::mock(CredentialProvider::class);
        $credentials->shouldNotReceive('removeCredentials');
        $this->app->instance(CredentialProvider::class, $credentials);

        $this->logout()->assertRedirect(Filament::getUrl());

        $this->assertGuest();
    }

    public function test_a_failing_credential_store_still_logs_out(): void
    {
        config()->set('atp-appview.auth.mode', 'backend');

        $credentials = Mockery::mock(CredentialProvider::class);
        $credentials->shouldReceive('removeCredentials')->andThrow(new RuntimeException('Store is down'));
        $this->app->instance(CredentialProvider::class, $credentials);

        $this->logout()->assertRedirect(Filament::getUrl());

        $this->assertGuest();
    }

    protected function logout(): TestResponse
    {
        $user = (new AtpUser())->forceFill(['id' => 1, 'did' => 'did:plc:alice']);

        return $this->actingAs($user)->post(Filament::getLogoutUrl());
    }
}

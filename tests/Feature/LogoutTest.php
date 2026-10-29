<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Testing\TestResponse;
use SocialDept\AtpAppview\Auth\SignOut;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Tests\Fixtures\AtpUser;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpClient\Facades\Atp;
use SocialDept\AtpClient\Testing\FakeAtpManager;

class LogoutTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AtpUser::class);
    }

    public function test_backend_mode_revokes_the_server_held_session(): void
    {
        config()->set('atp-appview.auth.mode', 'backend');
        $atp = Atp::fake();

        $this->logout()->assertRedirect(Filament::getUrl());

        $this->assertSame([['revoke', ['did' => 'did:plc:alice']]], $this->revoked($atp));
        $this->assertGuest();
    }

    public function test_frontend_mode_sends_the_browser_to_a_page_that_signs_it_out(): void
    {
        config()->set('atp-appview.auth.mode', 'frontend');
        AtpAppviewPlugin::get()->signOutUrl(fn () => url('/home'));
        $atp = Atp::fake();

        $this->logout()
            ->assertRedirect(url('/home'))
            ->assertSessionHas(SignOut::FLASH_KEY);

        $this->assertSame([], $this->revoked($atp), 'The browser holds the session, so the server revokes nothing.');
        $this->assertGuest();
    }

    public function test_frontend_mode_sends_the_browser_home_by_default(): void
    {
        config()->set('atp-appview.auth.mode', 'frontend');
        Atp::fake();

        $this->logout()
            ->assertRedirect(url('/'))
            ->assertSessionHas(SignOut::FLASH_KEY);

        $this->assertGuest();
    }

    public function test_sign_out_can_be_turned_off(): void
    {
        config()->set('atp-appview.auth.mode', 'backend');
        AtpAppviewPlugin::get()->signOut(false)->signOutUrl('/home');
        $atp = Atp::fake();

        $this->logout()
            ->assertRedirect(Filament::getUrl())
            ->assertSessionMissing(SignOut::FLASH_KEY);

        $this->assertSame([], $this->revoked($atp));
        $this->assertGuest();
    }

    /**
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    protected function revoked(FakeAtpManager $atp): array
    {
        return array_values(array_filter($atp->oauth()->recordedCalls(), fn (array $call) => $call[0] === 'revoke'));
    }

    protected function logout(): TestResponse
    {
        $user = (new AtpUser())->forceFill(['id' => 1, 'did' => 'did:plc:alice']);

        return $this->actingAs($user)->post(Filament::getLogoutUrl());
    }
}

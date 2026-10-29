<?php

namespace SocialDept\AtpAppviewFilament\Http\Controllers;

use Filament\Auth\Http\Controllers\LogoutController as FilamentLogoutController;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Http\Responses\BrowserSignOutResponse;
use SocialDept\AtpClient\Contracts\CredentialProvider;
use SocialDept\AtpClient\Contracts\HasAtpSession;
use Throwable;

/**
 * Filament's logout, extended to end the atproto session as well as the
 * Laravel one on panels that use the plugin. In backend mode the server
 * forgets the OAuth session it holds. In frontend mode the browser holds it,
 * so the browser is sent to a page that runs the browser library's signOut().
 */
class LogoutController extends FilamentLogoutController
{
    public function __invoke(): LogoutResponse
    {
        $plugin = $this->plugin();

        if ($plugin === null || ! $plugin->hasSignOut()) {
            return parent::__invoke();
        }

        $user = Filament::auth()->user();
        $did = $user instanceof HasAtpSession ? $user->getAtpDid() : null;

        if (is_string($did) && $did !== '' && $this->mode() === 'backend') {
            $this->forget($did);
        }

        $response = parent::__invoke();

        $url = $plugin->getSignOutUrl();

        return $this->mode() === 'frontend' && $url !== null
            ? new BrowserSignOutResponse($url)
            : $response;
    }

    protected function plugin(): ?AtpAppviewPlugin
    {
        $panel = Filament::getCurrentPanel();
        $plugin = $panel?->hasPlugin('atp-appview') ? $panel->getPlugin('atp-appview') : null;

        return $plugin instanceof AtpAppviewPlugin ? $plugin : null;
    }

    protected function mode(): string
    {
        return (string) config('atp-appview.auth.mode', 'frontend');
    }

    /**
     * Forget the OAuth session the server holds. Signing out still ends the
     * Laravel session when the store fails.
     */
    protected function forget(string $did): void
    {
        try {
            app(CredentialProvider::class)->removeCredentials($did);
        } catch (Throwable $e) {
            Log::warning('Could not forget the atproto session on logout', ['did' => $did, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace SocialDept\AtpAppviewFilament\Http\Controllers;

use Filament\Auth\Http\Controllers\LogoutController as FilamentLogoutController;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use SocialDept\AtpAppview\Auth\SignOut;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Http\Responses\BrowserSignOutResponse;

/**
 * Filament's logout, extended to end the atproto session as well as the
 * Laravel one on panels that use the plugin, through atp-appview's SignOut.
 * In backend mode the server revokes the OAuth session it holds. In frontend
 * mode the browser holds it, so the browser is sent to an app page that runs
 * atp-appview's browser library, which signs it out there.
 */
class LogoutController extends FilamentLogoutController
{
    public function __invoke(): LogoutResponse
    {
        $plugin = $this->plugin();

        if ($plugin === null || ! $plugin->hasSignOut()) {
            return parent::__invoke();
        }

        // SignOut ends the Laravel session itself, and in frontend mode
        // flashes the flag the next page hands the browser, so Filament's own
        // logout must not run after it and invalidate the session again.
        $result = app(SignOut::class)(request(), Filament::getAuthGuard());

        if ($result !== null && $result->hadSession() && ! $result->revoked) {
            Log::warning('Could not revoke the atproto session on logout', ['failure' => $result->failure?->name, 'error' => $result->error]);
        }

        return $this->mode() === 'frontend'
            ? new BrowserSignOutResponse($plugin->getSignOutUrl() ?? url('/'))
            : app(LogoutResponse::class);
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
}

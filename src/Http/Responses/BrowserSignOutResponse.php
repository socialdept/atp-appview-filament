<?php

namespace SocialDept\AtpAppviewFilament\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Sends the browser to the app's page that signs it out of its own atproto
 * session, in frontend mode.
 */
class BrowserSignOutResponse implements LogoutResponse
{
    public function __construct(protected string $url)
    {
    }

    public function toResponse($request): RedirectResponse
    {
        return redirect()->to($this->url);
    }
}

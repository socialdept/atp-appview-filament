# Signing out

Part of the [atp-appview-filament docs](../introduction.md#documentation). How the panel's logout ends the atproto session in backend and frontend mode.

Filament's logout ends the Laravel session only, which leaves the user's atproto session behind. On a panel with the plugin, logout goes through atp-appview's `SignOut` instead, which ends both, the way atp-appview's `auth.mode` says the session is held:

- **Backend mode**: the server holds the OAuth session, so logout revokes it at the authorization server through atp-client and forgets it. The user model implements atp-client's `HasAtpSession`, or has the DID in atp-appview's `auth.did_column`. A failed revocation is logged, and logout still goes ahead.
- **Frontend mode**: the browser holds the session, and only the browser can drop it. Logout ends the Laravel session and sends the browser to `signOutUrl()`, `/` unless set. Any app page that runs atp-appview's browser library will do: the page's `FrontendAuth::browserConfig()` carries the sign-out, and the library signs the browser out instead of starting a new server session. Panels do not run the library, so the page must be one of the app's own.

`signOutUrl()` takes a string or a closure that returns one. `signOut(false)` restores Filament's own logout. Panels without the plugin are left alone.

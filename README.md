<h1 align="center">ATP AppView Filament</h1>

<h3 align="center">
    Run and inspect AT Protocol AppViews from a Filament panel in Laravel.
</h3>

<p align="center">
    <br>
    <a href="https://packagist.org/packages/socialdept/atp-appview-filament" title="Latest Version on Packagist"><img src="https://img.shields.io/packagist/v/socialdept/atp-appview-filament.svg?style=flat-square"></a>
    <a href="https://github.com/socialdept/atp-appview-filament/actions/workflows/tests.yml" title="GitHub Tests Action Status"><img src="https://img.shields.io/github/actions/workflow/status/socialdept/atp-appview-filament/tests.yml?branch=main&label=tests&style=flat-square"></a>
    <a href="LICENSE" title="Software License"><img src="https://img.shields.io/github/license/socialdept/atp-appview-filament?style=flat-square"></a>
</p>

---

> [!WARNING]
> **In early development.** Nothing here is ready to use yet. Expect breaking changes until the first tagged release.

## What is ATP AppView Filament?

An operator panel for an AppView built on [atp-appview](https://github.com/socialdept/atp-appview), added to any Filament panel as a plugin. It covers the AT Protocol side of running an AppView: whether the network is flowing into the index, what the index holds, what it does to stay correct, and what is being backfilled.

## Requirements

- PHP 8.3+
- Laravel 11.28+, 12 or 13
- Filament 5
- atp-appview

## Installation

```bash
composer require socialdept/atp-appview-filament
php artisan filament:assets
```

Add the plugin to a panel:

```php
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AtpAppviewPlugin::make());
}
```

Who can reach the panel is up to the panel, through `canAccessPanel()` on the user model as usual.

## What it adds

- **Network, Index and Upkeep stats** on the dashboard, from atp-appview's `AppviewStatus`: whether Tap or Jetstream and the label streams are up and how far behind the index is, what the index holds, and how reconciliation, followed lexicons and the blocklist are doing. Each stat's second line adds a figure rather than restating the label.
- **A Collections table**: records, repos, hidden records and tombstones per collection, as `appview:stats` last counted them, with a button to count again. Each record count opens that collection on the Records page.
- **Backfill cards** on the dashboard: one per running backfill, and per one that ended in the last hour, with how many repos have delivered, records received, the rate, the time left, and why one failed.
- **A backfill notice** at the top of every other page while a backfill runs, since lists and counts may be missing records until it finishes.
- **Identities**: every repo the index knows, with its avatar, handle, PDS, account status, record count and what the reconciler last found, errors grouped by kind. Reconcile one repo or a selection now.
- **Identity pages**: each repo laid out as a social profile, with its banner, avatar, display name, handle, pronouns, bio and website from Profiles, its PDS, record and collection counts, and when it last wrote. Below, its records across collections, newest first, narrowed to one by clicking a collection. From its page: reconcile with a preview of what would change before anything is written, block or unblock, and retry or reindex its failed deliveries. Under More: refresh its handle, PDS and profile, resync it in Tap, see Tap's status for it, and links to its repo, Bluesky profile, PDS, PLC audit log and records. Everything destructive asks first.
- **Records**: the index a collection at a time, companion collections such as profiles included, filtered by repo, tombstones or hidden records, with each record in a JSON viewer.
- **Lexicons**: followed lexicons, the revision in use and any new version held back, with its breaking changes listed and a button to accept it anyway. Check for new versions from the page.
- **Blocklist**: block a repo by DID or every repo on a PDS host, optionally deleting what is already indexed, purge and unblock.
- **Labelers**: the labelers the index follows, through Tack or their own streams, with each one's profile, connection, labels kept, and labels received from Tack or the last label from its stream, and follow and unfollow. A labeler's page lists the values it declares with their names, severity, what they blur and the labeler's default, what each does here on content and on accounts, and how many are in force, with a Change action to set a policy or return to the default.
- **Moderation log**: every ban, review and takedown, by an operator or a label, with who, why, when, and when it was lifted.
- **Moderate menu** on identity pages: block, ban, needs review, keep or prune a banned account's records, and take down or restore it on a PDS you run. Badges on the profile show what is in force, the account's labels colored by what they do, and whether you host it. Records tables show each record's labels the same way.
- **Failed Deliveries**: events ingest parked because no handler could take them, with the error and the event in the JSON viewer. Retry once the cause in the app is fixed, reindex from the record's PDS once the record is, or forget them, one at a time or a selection. The navigation shows how many are parked. Below them, what ingest hooks rejected and ingest repairs rewrote, by class and collection.

The JSON viewer highlights the value and links what can be followed: AT-URIs and DIDs, web URLs, blobs on the repo's PDS, and a `$type` naming a followed lexicon.

## Options

```php
AtpAppviewPlugin::make()
    ->navigationGroup('AppView')   // or null for none
    ->dashboardWidgets(false)      // place the widgets yourself
    ->backfillNotice(false)        // no notice on every page
    ->identities(false)            // leave out a page: also records(),
                                   // lexicons(), blocklist(),
                                   // failedDeliveries(), labelers()
                                   // and moderation()
    ->recordLinks(fn (string $uri) => "https://pdsls.dev/{$uri}")
    ->signOutUrl('/sign-out')      // frontend mode, see Signing out
    ->signOut(false)               // logout ends the Laravel session only
```

The Labelers page, the Moderation log, the Moderate menu and the records' labels column follow atp-appview's moderation feature as well: when the app leaves `Features::moderation()` out of `atp-appview.features`, they do not show, whatever the options say.

AT-URIs and DIDs link to [pds.ls](https://pds.ls) unless `recordLinks()` says otherwise. The pages sit under `/appview/` in the panel's URL space.

## Signing out

Filament's logout ends the Laravel session only, which leaves the user's atproto session behind. On a panel with the plugin, logout ends that too, the way atp-appview's `auth.mode` says it is held:

- **Backend mode**: the server holds the OAuth session, so logout forgets it through atp-client's credential provider before ending the Laravel session. The user model implements `HasAtpSession` to name the DID.
- **Frontend mode**: the browser holds the session, and only the browser can drop it. Set `signOutUrl()` to a page in your app, open to guests, that calls `signOut()` from atp-appview's browser library, and logout sends the browser there once the Laravel session has ended. Call `signOut()` before anything runs `reconcile()` or `syncSessions()` on that page, or the browser starts a new server session from the tokens it still holds. Without `signOutUrl()`, logout ends the Laravel session as Filament does, and the next page that reconciles signs the browser back in.

```ts
// resources/js/pages/SignOut.vue, served at /sign-out
import { signOut } from '@socialdept/atp-appview';

await signOut();
window.location.assign('/');
```

`signOut(false)` restores Filament's own logout. Panels without the plugin are left alone.

The plugin's styles are built on Filament's color variables, so they follow the panel's colors and dark mode without a custom theme.

## License

MIT

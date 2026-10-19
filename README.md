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

- **Network, Index and Upkeep stats** on the dashboard, from atp-appview's `AppviewStatus`: whether Tap is up and how far behind the index is, what the index holds, and how reconciliation, followed lexicons and the blocklist are doing. Each stat's second line adds a figure rather than restating the label.
- **A Collections table**: records, repos, hidden records and tombstones per collection, as `appview:stats` last counted them, with a button to count again. Each record count opens that collection on the Records page.
- **Backfill cards** on the dashboard: one per running backfill, and per one that ended in the last hour, with how many repos have delivered, records received, the rate, the time left, and why one failed.
- **A backfill notice** at the top of every other page while a backfill runs, since lists and counts may be missing records until it finishes.
- **Identities**: every repo the index knows, with its avatar, handle, PDS, account status, record count and what the reconciler last found, errors grouped by kind. Reconcile one repo or a selection now.
- **Identity pages**: each repo laid out as a social profile, with its banner, avatar, display name, handle, pronouns, bio and website from Profiles, its PDS, record and collection counts, and when it last wrote. Below, its records across collections, newest first, narrowed to one by clicking a collection. Block or unblock the repo from its page, and find links to its repo, PDS, PLC audit log and records under More.
- **Records**: the index a collection at a time, companion collections such as profiles included, filtered by repo, tombstones or hidden records, with each record in a JSON viewer.
- **Lexicons**: followed lexicons, the revision in use and any new version held back, with its breaking changes listed and a button to accept it anyway. Check for new versions from the page.
- **Blocklist**: block a repo by DID or every repo on a PDS host, optionally deleting what is already indexed, purge and unblock.
- **Failed Deliveries**: events ingest parked because no handler could take them, with the error and the event in the JSON viewer. Retry once the cause in the app is fixed, reindex from the record's PDS once the record is, or forget them, one at a time or a selection. The navigation shows how many are parked. Below them, what ingest hooks rejected and ingest repairs rewrote, by class and collection.

The JSON viewer highlights the value and links what can be followed: AT-URIs and DIDs, web URLs, blobs on the repo's PDS, and a `$type` naming a followed lexicon.

## Options

```php
AtpAppviewPlugin::make()
    ->navigationGroup('AppView')   // or null for none
    ->dashboardWidgets(false)      // place the widgets yourself
    ->backfillNotice(false)        // no notice on every page
    ->identities(false)            // leave out a page: also records(),
                                   // lexicons(), blocklist() and
                                   // failedDeliveries()
    ->recordLinks(fn (string $uri) => "https://pdsls.dev/{$uri}")
```

AT-URIs and DIDs link to [pds.ls](https://pds.ls) unless `recordLinks()` says otherwise. The pages sit under `/appview/` in the panel's URL space.

The plugin's styles are built on Filament's color variables, so they follow the panel's colors and dark mode without a custom theme.

## License

MIT

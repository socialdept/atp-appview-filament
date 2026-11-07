# Introduction

**ATP AppView Filament** is an operator panel for an AppView built on [atp-appview](https://github.com/socialdept/atp-appview), added to any Filament panel as a plugin. It covers the AT Protocol side of running an AppView:

- **Dashboard:** Network, Index and Upkeep stats, a Collections table, and a card per backfill, with a notice on every other page while one runs.
- **Identities:** every repo the index knows, each with a page laid out as a social profile, its records, and actions to reconcile, refresh and resync it.
- **Records and lexicons:** the index a collection at a time with each record in a JSON viewer, and the followed lexicons with any new version held back.
- **Failed deliveries:** events ingest parked, to retry, reindex or forget, and what ingest hooks rejected and repairs rewrote.
- **Blocklist and moderation:** repos and hosts kept out of the index, the labelers the index follows and what their labels do, bans, reviews and takedowns, and a log of each.
- **Signing out:** the panel's logout ends the atproto session as well as the Laravel one, in either of atp-appview's auth modes.

The plugin's styles are built on Filament's color variables, so they follow the panel's colors and dark mode without a custom theme.

## A first look

Add the plugin to a panel, and its pages and dashboard widgets appear there:

```php
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AtpAppviewPlugin::make());
}
```

Every part can be turned off, and the dashboard widgets placed by hand:

```php
AtpAppviewPlugin::make()
    ->dashboardWidgets(false)
    ->backfillNotice(false)
    ->moderation(false);
```

## Documentation

Start with [Installation](getting-started/installation.md), then [Options](getting-started/options.md). The docs are grouped by area, and each group has an index of its own:

- **[Getting started](getting-started/README.md)**: [Installation](getting-started/installation.md), [Options](getting-started/options.md), [Signing out](getting-started/signing-out.md)
- **[The panel](panel/README.md)**: [Dashboard](panel/dashboard.md), [Identities](panel/identities.md), [Records and lexicons](panel/records.md), [Failed deliveries](panel/failed-deliveries.md)
- **[Moderation](moderation/README.md)**: [Blocklist](moderation/blocklist.md), [Labelers](moderation/labelers.md), [Moderating accounts](moderation/accounts.md)

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
- **A Collections table**: records, repos, hidden records and tombstones per collection, as `appview:stats` last counted them, with a button to count again.
- **Backfill cards** on the dashboard: one per running backfill, and per one that ended in the last hour, with how many repos have delivered, records received, the rate, the time left, and why one failed.
- **A backfill notice** at the top of every other page while a backfill runs, since lists and counts may be missing records until it finishes.

## Options

```php
AtpAppviewPlugin::make()
    ->navigationGroup('AppView')   // or null for none
    ->dashboardWidgets(false)      // place the widgets yourself
    ->backfillNotice(false)        // no notice on every page
```

The plugin's styles are built on Filament's color variables, so they follow the panel's colors and dark mode without a custom theme.

## License

MIT

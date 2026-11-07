# Installation

Part of the [atp-appview-filament docs](../introduction.md#documentation). Installing the package, publishing its assets and adding the plugin to a panel.

## Installing

```bash
composer require socialdept/atp-appview-filament
php artisan filament:assets
```

`filament:assets` publishes the plugin's stylesheet along with Filament's own. The app needs [atp-appview](https://github.com/socialdept/atp-appview) installed and set up, since every page reads its index.

## Configuration

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

The plugin has no config file of its own. Its [options](options.md) are methods on the plugin. Who can reach the panel is up to the panel, through `canAccessPanel()` on the user model as usual.

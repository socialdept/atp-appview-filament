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

This package is an operator panel for an AppView built on [atp-appview](https://github.com/socialdept/atp-appview), added to any Filament panel as a plugin. It shows whether the network is flowing into the index, what the index holds, what it does to stay correct and what is being backfilled, and it lets an operator reconcile, block and moderate accounts from the panel. To learn all about it, head over to [the documentation](https://socialde.pt/docs/atp-appview-filament).

Here are a few short examples of what you can do. Add the plugin to a panel:

```php
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AtpAppviewPlugin::make());
}
```

Put its pages in a navigation group of your own, or in none:

```php
AtpAppviewPlugin::make()
    ->navigationGroup('AppView');
```

Leave out the pages an app does not need:

```php
AtpAppviewPlugin::make()
    ->lexicons(false)
    ->labelers(false);
```

Send AT-URIs and DIDs somewhere other than pds.ls:

```php
AtpAppviewPlugin::make()
    ->recordLinks(fn (string $uri) => "https://pdsls.dev/{$uri}");
```

## Documentation

You'll find the documentation on [https://socialde.pt/docs/atp-appview-filament](https://socialde.pt/docs/atp-appview-filament).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving it? Feel free to [create an issue on GitHub](https://github.com/socialdept/atp-appview-filament/issues).

## Testing

```bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

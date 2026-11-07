# Options

Part of the [atp-appview-filament docs](../introduction.md#documentation). The plugin's options, from the navigation group to the pages it adds.

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
    ->signOutUrl('/home')          // frontend mode, see Signing out
    ->signOut(false)               // logout ends the Laravel session only
```

Every page and widget is on by default, in a navigation group named `AppView`.

## Pages

`identities()`, `records()`, `lexicons()`, `blocklist()`, `failedDeliveries()`, `labelers()` and `moderation()` each take `false` to leave that page out. `moderation(false)` leaves out the Moderation log and the ban, review and takedown actions on identity pages.

The Labelers page, the Moderation log and the moderation actions follow atp-appview's moderation feature as well. When the app leaves `Features::moderation()` out of `atp-appview.features`, they do not show, whatever the options say. The labels column on records tables goes with the feature too.

The pages sit under `/appview/` in the panel's URL space, such as `/appview/identities`.

## Dashboard widgets

With `dashboardWidgets()` on, the plugin adds its widgets to the panel's dashboard: `BackfillsWidget`, `NetworkOverview`, `IndexOverview`, `UpkeepOverview` and `CollectionsTable`, all in `SocialDept\AtpAppviewFilament\Widgets`. Turn it off to place them yourself. `backfillNotice(false)` drops the notice shown at the top of every other page while a backfill runs.

## Links

AT-URIs and DIDs link to [pds.ls](https://pds.ls) unless `recordLinks()` says otherwise. The closure gets an AT-URI, `at://{did}` for a repo, and returns the URL.

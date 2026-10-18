<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Widgets\Widget;
use SocialDept\AtpAppview\Backfill\Backfills;

/**
 * A card per running backfill, and per one that ended in the last hour, with
 * how far it has come. Renders nothing when there are none, but keeps polling
 * so a backfill that starts shows up.
 */
class BackfillsWidget extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'atp-appview-filament::widgets.backfills';

    protected function getViewData(): array
    {
        return ['backfills' => app(Backfills::class)->progress()];
    }
}

<?php

namespace SocialDept\AtpAppviewFilament\Tests\Fixtures;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->pages([Dashboard::class])
            ->plugin(AtpAppviewPlugin::make());
    }
}

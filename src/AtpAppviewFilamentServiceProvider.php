<?php

namespace SocialDept\AtpAppviewFilament;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialDept\AtpAppviewFilament\Livewire\BackfillNotice;

class AtpAppviewFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atp-appview-filament');

        Livewire::component('atp-appview-filament.backfill-notice', BackfillNotice::class);

        // Published with php artisan filament:assets, as Filament's own are.
        FilamentAsset::register([
            Css::make('atp-appview-filament', __DIR__.'/../resources/dist/atp-appview-filament.css'),
        ], 'socialdept/atp-appview-filament');
    }
}

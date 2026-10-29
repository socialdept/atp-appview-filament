<?php

namespace SocialDept\AtpAppviewFilament;

use Filament\Auth\Http\Controllers\LogoutController as FilamentLogoutController;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use SocialDept\AtpAppviewFilament\Http\Controllers\LogoutController;
use SocialDept\AtpAppviewFilament\Livewire\BackfillNotice;
use SocialDept\AtpAppviewFilament\Support\Avatars;
use SocialDept\AtpAppviewFilament\Support\RecordLabels;

class AtpAppviewFilamentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Once per request, so a page's avatars are looked up together.
        $this->app->scoped(Avatars::class);
        $this->app->scoped(RecordLabels::class);

        // The panel's logout ends the atproto session too. Panels without the
        // plugin get Filament's own logout.
        $this->app->bind(FilamentLogoutController::class, LogoutController::class);
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

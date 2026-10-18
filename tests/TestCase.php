<?php

namespace SocialDept\AtpAppviewFilament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use SocialDept\AtpAppview\AtpAppviewServiceProvider;
use SocialDept\AtpAppviewFilament\AtpAppviewFilamentServiceProvider;
use SocialDept\AtpAppviewFilament\Tests\Fixtures\AdminPanelProvider;
use SocialDept\AtpSchema\SchemaServiceProvider;
use SocialDept\AtpSupport\AtpSupportServiceProvider;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            AtpSupportServiceProvider::class,
            SchemaServiceProvider::class,
            AtpAppviewServiceProvider::class,
            AtpAppviewFilamentServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('atp-appview.ingest.admin_url', 'http://tap.test');
        $app['config']->set('atp-appview.ingest.collections', ['app.example.post', 'app.example.like']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
}

<?php

namespace SocialDept\AtpAppviewFilament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use SocialDept\AtpAppviewFilament\Widgets\BackfillsWidget;

/**
 * The AppView operator panel, added to a Filament panel:
 *
 *     $panel->plugin(AtpAppviewPlugin::make());
 */
class AtpAppviewPlugin implements Plugin
{
    protected ?string $navigationGroup = 'AppView';

    protected bool $dashboardWidgets = true;

    protected bool $backfillNotice = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'atp-appview';
    }

    /**
     * The navigation group the plugin's pages go in, or null for none.
     */
    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    /**
     * Whether the plugin's widgets go on the panel's dashboard. Turn it off to
     * place them yourself.
     */
    public function dashboardWidgets(bool $condition = true): static
    {
        $this->dashboardWidgets = $condition;

        return $this;
    }

    public function hasDashboardWidgets(): bool
    {
        return $this->dashboardWidgets;
    }

    /**
     * Whether every page says so while a backfill runs, since lists and counts
     * may be missing records until it finishes.
     */
    public function backfillNotice(bool $condition = true): static
    {
        $this->backfillNotice = $condition;

        return $this;
    }

    public function hasBackfillNotice(): bool
    {
        return $this->backfillNotice;
    }

    public function register(Panel $panel): void
    {
        if ($this->dashboardWidgets) {
            $panel->widgets([BackfillsWidget::class]);
        }

        if ($this->backfillNotice) {
            $panel->renderHook(PanelsRenderHook::PAGE_START, fn (): string => Blade::render('@livewire(\'atp-appview-filament.backfill-notice\')'));
        }
    }

    public function boot(Panel $panel): void
    {
    }
}

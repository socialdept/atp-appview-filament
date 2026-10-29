<?php

namespace SocialDept\AtpAppviewFilament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use SocialDept\AtpAppview\Features;
use SocialDept\AtpAppview\Features\Moderation;
use SocialDept\AtpAppviewFilament\Resources\AccountActions\AccountActionResource;
use SocialDept\AtpAppviewFilament\Resources\Blocks\BlockResource;
use SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\FailedDeliveryResource;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Resources\Labelers\LabelerResource;
use SocialDept\AtpAppviewFilament\Resources\Lexicons\LexiconResource;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Widgets\BackfillsWidget;
use SocialDept\AtpAppviewFilament\Widgets\CollectionsTable;
use SocialDept\AtpAppviewFilament\Widgets\IndexOverview;
use SocialDept\AtpAppviewFilament\Widgets\NetworkOverview;
use SocialDept\AtpAppviewFilament\Widgets\UpkeepOverview;

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

    protected bool $identities = true;

    protected bool $records = true;

    protected bool $lexicons = true;

    protected bool $blocklist = true;

    protected bool $failedDeliveries = true;

    protected bool $labelers = true;

    protected bool $moderation = true;

    protected bool $signOut = true;

    protected string|Closure|null $signOutUrl = null;

    /** @var (Closure(string): string)|null */
    protected ?Closure $recordLinks = null;

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

    /**
     * The Identities page: every repo the index knows, its account status and
     * what the reconciler last found.
     */
    public function identities(bool $condition = true): static
    {
        $this->identities = $condition;

        return $this;
    }

    /**
     * The Records page: the index a collection at a time.
     */
    public function records(bool $condition = true): static
    {
        $this->records = $condition;

        return $this;
    }

    /**
     * The Lexicons page: followed lexicons and versions held back.
     */
    public function lexicons(bool $condition = true): static
    {
        $this->lexicons = $condition;

        return $this;
    }

    /**
     * The Blocklist page: repos and hosts kept out of the index.
     */
    public function blocklist(bool $condition = true): static
    {
        $this->blocklist = $condition;

        return $this;
    }

    /**
     * The Failed Deliveries page: events ingest parked, to retry, reindex or
     * forget, and what hooks rejected and repairs rewrote.
     */
    public function failedDeliveries(bool $condition = true): static
    {
        $this->failedDeliveries = $condition;

        return $this;
    }

    /**
     * The Labelers page: the labelers Tack follows and what their labels do.
     */
    public function labelers(bool $condition = true): static
    {
        $this->labelers = $condition;

        return $this;
    }

    /**
     * The Moderation log, and the Moderate menu on identity pages.
     */
    public function moderation(bool $condition = true): static
    {
        $this->moderation = $condition;

        return $this;
    }

    /**
     * Whether the moderation pages and menus show: asked for here, and the
     * app's moderation feature on.
     */
    public function hasModeration(): bool
    {
        return $this->moderation && Features::enabled(Moderation::class);
    }

    /**
     * Whether the Labelers page shows: asked for here, and the app's
     * moderation feature on.
     */
    public function hasLabelers(): bool
    {
        return $this->labelers && Features::enabled(Moderation::class);
    }

    public function hasIdentities(): bool
    {
        return $this->identities;
    }

    public function hasRecords(): bool
    {
        return $this->records;
    }

    /**
     * Whether the panel's logout ends the atproto session too, through
     * atp-appview's SignOut: in backend mode the server revokes the OAuth
     * session it holds, and in frontend mode the browser goes to
     * signOutUrl(), where atp-appview's browser library drops its own.
     */
    public function signOut(bool $condition = true): static
    {
        $this->signOut = $condition;

        return $this;
    }

    public function hasSignOut(): bool
    {
        return $this->signOut;
    }

    /**
     * In frontend mode, the app page the panel's logout sends the browser to,
     * `/` by default. Any page that runs atp-appview's browser library will
     * do: it sees the sign-out SignOut flashed and signs the browser out.
     */
    public function signOutUrl(string|Closure|null $url): static
    {
        $this->signOutUrl = $url;

        return $this;
    }

    public function getSignOutUrl(): ?string
    {
        $url = $this->signOutUrl instanceof Closure ? ($this->signOutUrl)() : $this->signOutUrl;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Where AT-URIs and DIDs link, given an AT-URI. pds.ls by default.
     *
     * @param  Closure(string $uri): string  $url
     */
    public function recordLinks(Closure $url): static
    {
        $this->recordLinks = $url;

        return $this;
    }

    public function recordUrl(string $uri): string
    {
        return $this->recordLinks === null ? 'https://pds.ls/'.$uri : (string) ($this->recordLinks)($uri);
    }

    public function register(Panel $panel): void
    {
        $panel->resources(array_keys(array_filter([
            IdentityResource::class => $this->identities,
            RecordResource::class => $this->records,
            LexiconResource::class => $this->lexicons,
            BlockResource::class => $this->blocklist,
            FailedDeliveryResource::class => $this->failedDeliveries,
            LabelerResource::class => $this->hasLabelers(),
            AccountActionResource::class => $this->hasModeration(),
        ])));

        if ($this->dashboardWidgets) {
            $panel->widgets([
                BackfillsWidget::class,
                NetworkOverview::class,
                IndexOverview::class,
                UpkeepOverview::class,
                CollectionsTable::class,
            ]);
        }

        if ($this->backfillNotice) {
            $panel->renderHook(PanelsRenderHook::PAGE_START, fn (): string => Blade::render('@livewire(\'atp-appview-filament.backfill-notice\')'));
        }
    }

    public function boot(Panel $panel): void
    {
    }
}

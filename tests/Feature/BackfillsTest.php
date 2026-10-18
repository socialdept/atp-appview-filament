<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Livewire\Livewire;
use SocialDept\AtpAppview\Backfill\Backfills;
use SocialDept\AtpAppview\Models\Backfill;
use SocialDept\AtpAppview\Models\BackfillRepo;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Livewire\BackfillNotice;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpAppviewFilament\Widgets\BackfillsWidget;

class BackfillsTest extends TestCase
{
    protected const READS = 'app.example.read';

    /**
     * @param  array<string, bool>  $repos  Whether each repo delivered
     */
    protected function backfill(array $repos, string $status = Backfill::RUNNING, ?string $error = null): Backfill
    {
        $backfill = Backfill::query()->create([
            'collection' => self::READS,
            'source' => 'test',
            'status' => $status,
            'error' => $error,
            'started_at' => now(),
            'finished_at' => $status === Backfill::RUNNING ? null : now(),
        ]);

        foreach ($repos as $did => $delivered) {
            BackfillRepo::query()->insert(['backfill_id' => $backfill->id, 'did' => $did, 'method' => 'resync', 'delivered_at' => $delivered ? now() : null]);
        }

        app(Backfills::class)->forget();

        return $backfill;
    }

    public function test_the_plugin_puts_its_widget_on_the_dashboard_and_the_notice_on_every_page(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertContains(BackfillsWidget::class, $panel->getWidgets());
        $hooks = (fn () => $this->renderHooks)->call($panel);
        $this->assertArrayHasKey(PanelsRenderHook::PAGE_START, $hooks);
        $this->assertSame('atp-appview', AtpAppviewPlugin::get()->getId());
    }

    public function test_the_widget_and_notice_can_be_left_out(): void
    {
        $plugin = AtpAppviewPlugin::make()->dashboardWidgets(false)->backfillNotice(false)->navigationGroup(null);

        $this->assertFalse($plugin->hasDashboardWidgets());
        $this->assertFalse($plugin->hasBackfillNotice());
        $this->assertNull($plugin->getNavigationGroup());
    }

    public function test_the_stylesheet_is_registered(): void
    {
        $this->assertNotEmpty(collect(FilamentAsset::getStyles(['socialdept/atp-appview-filament']))->filter(fn ($style) => $style->getId() === 'atp-appview-filament'));
    }

    public function test_the_widget_shows_a_card_per_backfill(): void
    {
        Livewire::test(BackfillsWidget::class)->assertDontSee(self::READS);

        $this->backfill(['did:plc:a' => true, 'did:plc:b' => false]);

        Livewire::test(BackfillsWidget::class)
            ->assertSee(self::READS)
            ->assertSee('Backfilling')
            ->assertSee('50%')
            ->assertSee('1 of 2 repos');
    }

    public function test_a_finished_card_explains_what_is_left_and_a_failed_one_says_why(): void
    {
        $this->backfill(['did:plc:a' => true, 'did:plc:b' => false], Backfill::FINISHED);

        Livewire::test(BackfillsWidget::class)->assertSee('Finished')->assertSee('1 had nothing to send');

        Backfill::query()->delete();
        $this->backfill(['did:plc:a' => false], Backfill::FAILED, "Tap delivered nothing for app.example.read. Check that it is in Tap's collection filters, then run appview:backfill again.");

        Livewire::test(BackfillsWidget::class)
            ->assertSee('Failed')
            ->assertSee('of 1 repos delivered')
            ->assertDontSee('had nothing to send')
            ->assertSee('Check that it is in Tap');
    }

    public function test_the_notice_shows_only_while_a_backfill_runs(): void
    {
        Livewire::test(BackfillNotice::class)->assertDontSee('Backfill in progress');

        $this->backfill(['did:plc:a' => false]);

        Livewire::test(BackfillNotice::class)->assertSee('Backfill in progress')->assertSee(self::READS.' is still being backfilled');
    }
}

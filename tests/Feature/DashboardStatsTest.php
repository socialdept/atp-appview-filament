<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use SocialDept\AtpAppview\Index\IndexStats;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpAppviewFilament\Widgets\BackfillsWidget;
use SocialDept\AtpAppviewFilament\Widgets\CollectionsTable;
use SocialDept\AtpAppviewFilament\Widgets\IndexOverview;
use SocialDept\AtpAppviewFilament\Widgets\NetworkOverview;
use SocialDept\AtpAppviewFilament\Widgets\UpkeepOverview;

class DashboardStatsTest extends TestCase
{
    protected function record(string $did, string $collection, string $rkey, array $state = []): void
    {
        DB::table('atp_records')->insert([
            'uri' => "at://{$did}/{$collection}/{$rkey}", 'did' => $did, 'collection' => $collection, 'rkey' => $rkey,
            'cid' => "cid-{$rkey}", 'record' => '{}', 'rev' => '3l2aaaaaaaaaa', 'indexed_at' => now(), 'sort_at' => now(), ...$state,
        ]);
    }

    public function test_the_plugin_puts_every_widget_on_the_dashboard(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        foreach ([BackfillsWidget::class, NetworkOverview::class, IndexOverview::class, UpkeepOverview::class, CollectionsTable::class] as $widget) {
            $this->assertContains($widget, $widgets);
        }
    }

    public function test_network_reports_tap(): void
    {
        Http::fake([
            'tap.test/health' => Http::response('ok'),
            'tap.test/stats/repo-count' => Http::response(['repo_count' => 18159]),
            'tap.test/stats/outbox-buffer' => Http::response(['outbox_buffer' => 0]),
            'tap.test/stats/resync-buffer' => Http::response(['resync_buffer' => 286]),
            'tap.test/stats/cursors' => Http::response(['firehose' => 34_600_000_000]),
        ]);

        Livewire::test(NetworkOverview::class)
            ->assertSee('Repos followed')
            ->assertSee('18,159')
            ->assertSee('Nothing queued')
            ->assertSee('286')
            ->assertSee('Cursor 34.60B');
    }

    public function test_network_says_so_when_tap_is_down(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);

        Livewire::test(NetworkOverview::class)->assertSee('Unreachable')->assertDontSee('Repos followed');
    }

    public function test_index_reports_what_it_holds(): void
    {
        $this->record('did:plc:alice', 'app.example.post', 'a');
        $this->record('did:plc:alice', 'app.example.post', 'b', ['deleted_at' => now(), 'record' => null, 'cid' => null]);
        $this->record('did:plc:bob', 'app.example.post', 'c', ['hidden_at' => now()]);
        Identity::query()->insert([
            ['did' => 'did:plc:alice', 'active' => true, 'updated_at' => now()],
            ['did' => 'did:plc:bob', 'active' => false, 'updated_at' => now()],
        ]);
        DB::table('atp_identities')->where('did', 'did:plc:bob')->update(['status' => 'deactivated']);

        Livewire::test(IndexOverview::class)
            ->assertSee('Records')
            ->assertSee('+1 in 24 h')
            ->assertSee('1 deactivated')
            ->assertSee('+1 hidden')
            ->assertSee('Postgres only');
    }

    public function test_upkeep_names_the_commonest_reconcile_error_in_words(): void
    {
        Identity::query()->insert([
            ['did' => 'did:plc:a', 'active' => true, 'updated_at' => now(), 'reconciled_at' => now(), 'reconcile_error' => 'UnknownError: something broke'],
            ['did' => 'did:plc:b', 'active' => true, 'updated_at' => now(), 'reconciled_at' => now(), 'reconcile_error' => 'UnknownError: again'],
            ['did' => 'did:plc:c', 'active' => true, 'updated_at' => now(), 'reconciled_at' => now(), 'reconcile_error' => null],
        ]);

        Livewire::test(UpkeepOverview::class)
            ->assertSee('Reconciled')
            ->assertSee('100%')
            ->assertSee('2 unknown error')
            ->assertSee('Lexicons followed');
    }

    public function test_collections_list_every_ingested_collection_and_can_count_again(): void
    {
        $this->record('did:plc:alice', 'app.example.post', 'a');
        app(IndexStats::class)->refresh();
        $this->record('did:plc:alice', 'app.example.post', 'b');

        Livewire::test(CollectionsTable::class)
            ->assertSee('app.example.post')
            ->assertSee('app.example.like')
            ->callTableAction('count');

        $this->assertSame(2, app(IndexStats::class)->totals()['records']);
    }
}

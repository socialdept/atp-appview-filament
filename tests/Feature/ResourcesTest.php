<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use SocialDept\AtpAppview\Index\Reconciler;
use SocialDept\AtpAppview\Index\ReconcileResult;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\FollowedLexicon;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Resources\Blocks\BlockResource;
use SocialDept\AtpAppviewFilament\Resources\Blocks\Pages\ListBlocks;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Resources\Identities\Pages\ListIdentities;
use SocialDept\AtpAppviewFilament\Resources\Lexicons\LexiconResource;
use SocialDept\AtpAppviewFilament\Resources\Lexicons\Pages\ListLexicons;
use SocialDept\AtpAppviewFilament\Resources\Records\Pages\ListRecords;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpAppviewFilament\Widgets\CollectionsTable;

class ResourcesTest extends TestCase
{
    protected const POSTS = 'app.example.post';
    protected const LIKES = 'app.example.like';

    protected function record(string $did, string $collection, string $rkey, array $value = [], array $state = []): void
    {
        DB::table('atp_records')->insert([
            'uri' => "at://{$did}/{$collection}/{$rkey}", 'did' => $did, 'collection' => $collection, 'rkey' => $rkey,
            'cid' => "cid-{$rkey}", 'record' => json_encode($value), 'rev' => '3l2aaaaaaaaaa', 'indexed_at' => now(), 'sort_at' => now(), ...$state,
        ]);
    }

    protected function identity(string $did, array $state = []): Identity
    {
        Identity::query()->insert(['did' => $did, 'active' => true, 'updated_at' => now(), ...$state]);

        return Identity::query()->findOrFail($did);
    }

    public function test_the_plugin_registers_every_resource_in_its_navigation_group(): void
    {
        $this->assertEqualsCanonicalizing(
            [IdentityResource::class, RecordResource::class, LexiconResource::class, BlockResource::class],
            Filament::getPanel('admin')->getResources(),
        );
        $this->assertSame('AppView', IdentityResource::getNavigationGroup());
    }

    public function test_each_resource_can_be_left_out(): void
    {
        $panel = Filament::getPanel('admin');
        $before = $panel->getResources();

        AtpAppviewPlugin::make()->identities(false)->records(false)->lexicons(false)->blocklist(false)->dashboardWidgets(false)->backfillNotice(false)->register($panel);

        $this->assertSame($before, $panel->getResources(), 'Leaving every resource out adds none.');
        $this->assertFalse(AtpAppviewPlugin::make()->records(false)->hasRecords());
    }

    public function test_identities_list_status_records_and_reconcile_errors(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test', 'reconciled_at' => now()]);
        $this->identity('did:plc:bob', ['handle' => 'bob.test', 'reconciled_at' => now(), 'reconcile_error' => 'cURL error 6: Could not resolve host']);
        $this->record('did:plc:alice', self::POSTS, 'a');

        Livewire::test(ListIdentities::class)
            ->assertCanSeeTableRecords(Identity::query()->get())
            ->assertSee('@alice.test')
            ->assertSee('Host not found')
            ->filterTable('reconcile_error', true)
            ->assertCanSeeTableRecords([Identity::query()->find('did:plc:bob')])
            ->assertCanNotSeeTableRecords([Identity::query()->find('did:plc:alice')]);
    }

    public function test_identities_count_records_for_the_page_shown_not_every_row_the_offset_skips(): void
    {
        foreach (range(1, 25) as $n) {
            $did = sprintf('did:plc:repo%03d', $n);
            $this->identity($did, ['handle' => "repo{$n}.test"]);

            for ($r = 1; $r <= $n % 3; $r++) {
                $this->record($did, self::POSTS, "{$n}-{$r}");
            }
        }

        $page = Livewire::test(ListIdentities::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $page->call('gotoPage', 3);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $listing = collect($queries)->first(fn (string $sql) => str_contains($sql, 'from "atp_identities"') && str_contains($sql, 'offset'));
        $this->assertNotNull($listing);
        $this->assertStringNotContainsString('records_count', $listing);
        $page->assertCanSeeTableRecords(Identity::query()->whereIn('did', ['did:plc:repo021', 'did:plc:repo025'])->get());
    }

    public function test_records_list_without_counting_the_whole_collection(): void
    {
        $this->record('did:plc:alice', self::POSTS, 'a');

        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListRecords::class);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertEmpty(array_filter($queries, fn (string $sql) => str_contains($sql, 'count(*)') && str_contains($sql, 'atp_records')));
    }

    public function test_reconcile_now_records_the_outcome_through_the_reconciler(): void
    {
        $identity = $this->identity('did:plc:alice', ['handle' => 'alice.test']);
        $reconciler = Mockery::mock(Reconciler::class);
        $reconciler->shouldReceive('reconcileAndRecord')->once()->with('did:plc:alice')->andReturn(new ReconcileResult('did:plc:alice', rev: '3l2', indexed: ['x']));
        $this->app->instance(Reconciler::class, $reconciler);

        Livewire::test(ListIdentities::class)
            ->callTableAction('reconcile', $identity)
            ->assertNotified('Reconciled');
    }

    public function test_a_failed_reconcile_says_why(): void
    {
        $identity = $this->identity('did:plc:alice');
        $reconciler = Mockery::mock(Reconciler::class);
        $reconciler->shouldReceive('reconcileAndRecord')->andThrow(new RuntimeException('PDS unreachable'));
        $this->app->instance(Reconciler::class, $reconciler);

        Livewire::test(ListIdentities::class)
            ->callTableAction('reconcile', $identity)
            ->assertNotified('Could not reconcile');
    }

    public function test_records_show_one_collection_at_a_time_with_a_readable_title(): void
    {
        $this->record('did:plc:alice', self::POSTS, 'a', ['title' => 'Hello world']);
        $this->record('did:plc:alice', self::POSTS, 'b', ['status' => '🙂']);
        $this->record('did:plc:alice', self::LIKES, 'c');

        Livewire::test(ListRecords::class)
            ->assertSee('Hello world')
            ->assertSee('🙂')
            ->assertCanNotSeeTableRecords(Record::query()->where('collection', self::LIKES)->get())
            ->filterTable('collection', self::LIKES)
            ->assertCanSeeTableRecords(Record::query()->where('collection', self::LIKES)->get());
    }

    public function test_companion_collections_can_be_browsed_too(): void
    {
        config(['atp-appview.ingest.companions' => ['app.example.profile']]);
        $this->record('did:plc:alice', 'app.example.profile', 'self', ['displayName' => 'Alice']);

        $page = Livewire::test(ListRecords::class);

        $this->assertArrayHasKey('app.example.profile', $page->instance()->getTable()->getFilter('collection')->getOptions());
        $page->filterTable('collection', 'app.example.profile')->assertSee('Alice');
    }

    public function test_the_json_viewer_links_records_repos_blobs_and_followed_lexicons(): void
    {
        FollowedLexicon::query()->insert(['nsid' => self::POSTS, 'did' => 'did:plc:author', 'uri' => 'at://did:plc:author/com.atproto.lexicon.schema/'.self::POSTS, 'schema' => '{}', 'revision' => 1, 'fetched_at' => now()]);

        $html = Json::render([
            '$type' => self::POSTS,
            'subject' => 'at://did:plc:bob/app.example.post/x',
            'site' => 'https://example.com/a.',
            'image' => ['$type' => 'blob', 'ref' => ['$link' => 'bafyblob'], 'mimeType' => 'image/png'],
        ], 'did:plc:alice', 'https://pds.example')->toHtml();

        $this->assertStringContainsString('class="atp-json"', $html);
        $this->assertStringContainsString('href="https://pds.ls/at://did:plc:bob/app.example.post/x"', $html);
        $this->assertStringContainsString('href="https://example.com/a"', $html, 'Trailing punctuation stays out of a URL.');
        $this->assertStringContainsString('href="https://pds.example/xrpc/com.atproto.sync.getBlob?did=did%3Aplc%3Aalice&amp;cid=bafyblob"', $html);
        $this->assertStringContainsString('href="https://pds.ls/at://did:plc:author/com.atproto.lexicon.schema/'.self::POSTS.'"', $html);
        $this->assertStringNotContainsString('style="color', $html, 'Colors come from the stylesheet.');
    }

    public function test_lexicons_list_followed_ones_and_explain_a_held_back_version(): void
    {
        config(['atp-appview.lexicons.follow' => [self::POSTS]]);
        FollowedLexicon::query()->insert([
            'nsid' => self::POSTS, 'did' => 'did:plc:author', 'uri' => 'at://did:plc:author/com.atproto.lexicon.schema/'.self::POSTS,
            'schema' => '{"lexicon":1,"id":"app.example.post","defs":{"main":{"type":"record","description":"A post."}}}', 'revision' => 2, 'fetched_at' => now(),
            'pending_cid' => 'bafy-next', 'pending_schema' => '{}',
            'pending_issues' => json_encode([['nsid' => self::POSTS, 'level' => 'error', 'name' => 'removed-field', 'message' => 'text was removed']]),
        ]);
        FollowedLexicon::query()->insert(['nsid' => self::LIKES, 'did' => 'did:plc:author', 'uri' => 'at://x', 'schema' => '{}', 'revision' => 1, 'fetched_at' => now()]);

        Livewire::test(ListLexicons::class)
            ->assertCanSeeTableRecords([FollowedLexicon::query()->find(self::POSTS)])
            ->assertCanNotSeeTableRecords([FollowedLexicon::query()->find(self::LIKES)])
            ->assertSee('A post.')
            ->assertSee('1 breaking change');

        $issues = LexiconResource::issues(FollowedLexicon::query()->find(self::POSTS))->toHtml();
        $this->assertStringContainsString('removed-field', $issues);
        $this->assertStringContainsString('text was removed', $issues);
        $this->assertSame('1', LexiconResource::getNavigationBadge());
    }

    public function test_the_blocklist_blocks_a_repo(): void
    {
        Livewire::test(ListBlocks::class)
            ->callTableAction('block', data: ['subject' => 'did:plc:spam', 'collection' => '', 'note' => '', 'purge' => false])
            ->assertNotified();

        $this->assertSame('did:plc:spam', Block::query()->sole()->subject);
    }

    public function test_collection_counts_link_to_their_records(): void
    {
        Livewire::test(CollectionsTable::class)
            ->assertSeeHtml(e(RecordResource::getUrl('index', ['filters' => ['collection' => ['value' => self::POSTS]]])));
    }

    public function test_record_links_can_point_elsewhere(): void
    {
        AtpAppviewPlugin::get()->recordLinks(fn (string $uri) => 'https://example.test/'.$uri);

        $this->assertSame('https://example.test/at://did:plc:a', AtpAppviewPlugin::get()->recordUrl('at://did:plc:a'));
    }
}

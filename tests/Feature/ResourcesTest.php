<?php

namespace SocialDept\AtpAppviewFilament\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use SocialDept\AtpAppview\Index\IdentityRefresh;
use SocialDept\AtpAppview\Index\Reconciler;
use SocialDept\AtpAppview\Index\ReconcileResult;
use SocialDept\AtpAppview\Ingest\Events\RecordAction;
use SocialDept\AtpAppview\Ingest\Events\RecordEvent;
use SocialDept\AtpAppview\Ingest\FailedDeliveries;
use SocialDept\AtpAppview\Ingest\Rejections;
use SocialDept\AtpAppview\Ingest\Repairs;
use SocialDept\AtpAppview\Ingest\TapAdmin;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\FailedDelivery;
use SocialDept\AtpAppview\Models\FollowedLexicon;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppview\Profiles\Profiles;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Resources\Blocks\BlockResource;
use SocialDept\AtpAppviewFilament\Resources\Blocks\Pages\ListBlocks;
use SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\FailedDeliveryResource;
use SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\Pages\ListFailedDeliveries;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Resources\Identities\Pages\ListIdentities;
use SocialDept\AtpAppviewFilament\Resources\Identities\Pages\ViewIdentity;
use SocialDept\AtpAppviewFilament\Resources\Lexicons\LexiconResource;
use SocialDept\AtpAppviewFilament\Resources\Lexicons\Pages\ListLexicons;
use SocialDept\AtpAppviewFilament\Resources\Records\Pages\ListRecords;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Support\Avatars;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use SocialDept\AtpAppviewFilament\Tests\TestCase;
use SocialDept\AtpAppviewFilament\Widgets\CollectionsTable;
use SocialDept\AtpAppviewFilament\Widgets\IdentityRecords;
use SocialDept\AtpAppviewFilament\Widgets\IngestTallies;

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
            [IdentityResource::class, RecordResource::class, LexiconResource::class, BlockResource::class, FailedDeliveryResource::class],
            Filament::getPanel('admin')->getResources(),
        );
        $this->assertSame('AppView', IdentityResource::getNavigationGroup());
    }

    public function test_each_resource_can_be_left_out(): void
    {
        $panel = Filament::getPanel('admin');
        $before = $panel->getResources();

        AtpAppviewPlugin::make()->identities(false)->records(false)->lexicons(false)->blocklist(false)->failedDeliveries(false)->dashboardWidgets(false)->backfillNotice(false)->register($panel);

        $this->assertSame($before, $panel->getResources(), 'Leaving every resource out adds none.');
        $this->assertFalse(AtpAppviewPlugin::make()->records(false)->hasRecords());
    }

    public function test_identities_show_avatars_looked_up_once_per_page(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test']);
        $this->identity('did:plc:bob', ['handle' => 'bob.test']);
        $profiles = Mockery::mock(Profiles::class);
        $profiles->shouldReceive('basic')->once()
            ->withArgs(fn (array $dids) => count($dids) === 2)
            ->andReturn(['did:plc:alice' => ['avatar' => 'https://img.example/alice.jpg'], 'did:plc:bob' => ['avatar' => null]]);
        $this->app->instance(Profiles::class, $profiles);

        Livewire::test(ListIdentities::class)
            ->assertSeeHtml('https://img.example/alice.jpg')
            ->assertSeeHtml(Avatars::placeholder('bob.test'));
    }

    public function test_an_identity_page_is_laid_out_as_its_profile_with_its_records(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test', 'pds' => 'https://pds.example']);
        $this->record('did:plc:alice', self::POSTS, 'a', ['text' => 'first post'], ['sort_at' => '2025-03-01 00:00:00']);
        $this->record('did:plc:alice', self::POSTS, 'b', ['text' => 'second post']);
        $this->record('did:plc:alice', self::LIKES, 'c');
        $this->record('did:plc:bob', self::POSTS, 'd', ['text' => 'not alice']);
        $profiles = Mockery::mock(Profiles::class);
        $profiles->shouldReceive('for')->andReturn(['did:plc:alice' => [
            'displayName' => 'Alice', 'description' => "Hello\nwww.alice.example", 'avatar' => 'https://img.example/a.jpg',
            'banner' => 'https://img.example/b.jpg', 'pronouns' => 'she/her',
        ]]);
        $this->app->instance(Profiles::class, $profiles);

        $page = Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->assertSee('Alice')
            ->assertSee('@alice.test')
            ->assertSee('she/her')
            ->assertSeeHtml('href="https://www.alice.example"')
            ->assertSeeHtml('https://img.example/b.jpg')
            ->assertSee('pds.example')
            ->assertSee('Records since Mar 2025')
            ->assertSeeHtml('<strong>3</strong> records')
            ->assertSeeHtml('<strong>2</strong> collections');

        $this->assertSame('Alice', $page->instance()->getTitle());
        $this->assertSame('Alice', array_values($page->instance()->getBreadcrumbs())[1]);

        Livewire::test(IdentityRecords::class, ['did' => 'did:plc:alice'])
            ->assertSee('first post')
            ->assertSee('second post')
            ->assertDontSee('not alice')
            // A collection chosen on the profile.
            ->dispatch('atp-identity-collection', collection: self::LIKES)
            ->assertDontSee('first post');
    }

    public function test_an_identity_is_blocked_and_unblocked_from_its_page(): void
    {
        Http::fake();
        $this->identity('did:plc:spam', ['handle' => 'spam.test']);
        $this->record('did:plc:spam', self::POSTS, 'a', ['text' => 'buy now']);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:spam'])
            ->assertActionVisible('block')
            ->assertActionHidden('unblock')
            ->callAction('block', data: ['collection' => '', 'note' => 'spam', 'purge' => true])
            ->assertNotified()
            ->assertSee('Blocked')
            ->assertActionHidden('block')
            ->assertActionVisible('unblock');

        $this->assertSame('spam', Block::query()->where('subject', 'did:plc:spam')->sole()->note);
        $this->assertSame(0, Record::query()->where('did', 'did:plc:spam')->count(), 'Purged.');

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:spam'])
            ->callAction('unblock')
            ->assertActionVisible('block');

        $this->assertSame(0, Block::query()->count());
    }

    public function test_an_identity_page_keeps_its_links_under_more(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test', 'pds' => 'https://pds.example']);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->assertActionVisible('reconcile')
            ->assertActionVisible('profile')
            ->assertActionHasUrl('open', Links::repo('did:plc:alice'))
            ->assertActionHasUrl('bluesky', 'https://bsky.app/profile/did:plc:alice')
            ->assertActionHasUrl('pds', 'https://pds.example')
            ->assertActionHasUrl('plc', 'https://plc.directory/did:plc:alice/log/audit')
            ->assertActionHasUrl('records', RecordResource::getUrl('index', ['filters' => ['did' => ['value' => 'did:plc:alice']]]));
    }

    public function test_reconcile_on_an_identity_page_previews_before_it_applies(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test']);
        $reconciler = Mockery::mock(Reconciler::class);
        $reconciler->shouldReceive('reconcile')->with('did:plc:alice', true)
            ->andReturn(new ReconcileResult('did:plc:alice', '3l2a', 12, [], ['at://did:plc:alice/app.example.post/gone']));
        $reconciler->shouldReceive('reconcileAndRecord')->once()->with('did:plc:alice')
            ->andReturn(new ReconcileResult('did:plc:alice', '3l2a', 12, [], ['at://did:plc:alice/app.example.post/gone']));
        $this->app->instance(Reconciler::class, $reconciler);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->mountAction('reconcile')
            ->assertMountedActionModalSee('To tombstone, gone from the PDS')
            ->assertMountedActionModalSee('at://did:plc:alice/app.example.post/gone')
            ->callMountedAction()
            ->assertNotified('Reconciled');
    }

    public function test_reconcile_offers_nothing_to_apply_when_the_repo_is_in_step(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test']);
        $reconciler = Mockery::mock(Reconciler::class);
        $reconciler->shouldReceive('reconcile')->andReturn(new ReconcileResult('did:plc:alice', '3l2a', 12));
        $reconciler->shouldNotReceive('reconcileAndRecord');
        $this->app->instance(Reconciler::class, $reconciler);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->mountAction('reconcile')
            ->assertMountedActionModalSee('In step with its PDS')
            ->assertMountedActionModalDontSee('Apply');
    }

    public function test_an_identity_with_failed_deliveries_can_retry_them_from_its_page(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test']);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])->assertActionHidden('failures');

        $delivery = $this->failedDelivery();
        $service = Mockery::mock(FailedDeliveries::class);
        $service->shouldReceive('reindex')->once()->withArgs(fn ($d) => $d->is($delivery))->andReturn(true);
        $this->app->instance(FailedDeliveries::class, $service);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->assertActionVisible('failures')
            ->assertActionHasLabel('failures', 'Retry failures (1)')
            ->callAction('failures', data: ['how' => 'reindex'])
            ->assertNotified('1 went through');
    }

    public function test_an_identity_is_refreshed_and_resynced_from_more(): void
    {
        $this->identity('did:plc:alice', ['handle' => 'alice.test']);
        $refresh = Mockery::mock(IdentityRefresh::class);
        $refresh->shouldReceive('refresh')->once()->with('did:plc:alice')
            ->andReturn(['handle' => 'alice.new', 'pds' => null, 'changed' => ['handle' => ['alice.test', 'alice.new']]]);
        $this->app->instance(IdentityRefresh::class, $refresh);
        $tap = Mockery::mock(TapAdmin::class);
        $tap->shouldReceive('resyncRepo')->once()->with('did:plc:alice')->andReturn('resyncing');
        $tap->shouldReceive('info')->with('did:plc:alice')->andReturn(['did' => 'did:plc:alice', 'state' => 'active']);
        $this->app->instance(TapAdmin::class, $tap);

        Livewire::test(ViewIdentity::class, ['record' => 'did:plc:alice'])
            ->callAction('refresh')
            ->assertNotified('Identity refreshed')
            ->callAction('resync')
            ->assertNotified('Resync queued')
            ->mountAction('tap')
            ->assertMountedActionModalSee('active');
    }

    public function test_an_identity_without_a_display_name_is_named_by_its_handle(): void
    {
        $this->identity('did:plc:bob', ['handle' => 'bob.test']);
        $profiles = Mockery::mock(Profiles::class);
        $profiles->shouldReceive('for')->andReturn(['did:plc:bob' => ['displayName' => '', 'avatar' => null, 'banner' => null]]);
        $this->app->instance(Profiles::class, $profiles);

        $this->assertSame('bob.test', Livewire::test(ViewIdentity::class, ['record' => 'did:plc:bob'])->instance()->getTitle());
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

    protected function failedDelivery(string $kind = 'record', string $rkey = 'bad'): FailedDelivery
    {
        return FailedDelivery::query()->create([
            'kind' => $kind, 'did' => 'did:plc:alice',
            'collection' => $kind === 'record' ? self::POSTS : null,
            'uri' => $kind === 'record' ? 'at://did:plc:alice/'.self::POSTS.'/'.$rkey : null,
            'payload' => ['id' => 1, 'did' => 'did:plc:alice', 'rkey' => $rkey], 'error_class' => RuntimeException::class,
            'error' => 'Picky refuses '.$rkey, 'failed_at' => now(),
        ]);
    }

    public function test_failed_deliveries_list_their_errors_and_count_in_the_navigation(): void
    {
        $this->assertNull(FailedDeliveryResource::getNavigationBadge(), 'No badge while there are none.');
        $record = $this->failedDelivery();
        $identity = $this->failedDelivery('identity');

        Livewire::test(ListFailedDeliveries::class)
            ->assertCanSeeTableRecords([$record, $identity])
            ->assertSee('Picky refuses bad')
            ->assertSee('RuntimeException')
            ->assertTableActionVisible('reindex', $record)
            ->assertTableActionHidden('reindex', $identity)
            ->filterTable('kind', 'identity')
            ->assertCanNotSeeTableRecords([$record]);

        $this->assertSame('2', FailedDeliveryResource::getNavigationBadge());
    }

    public function test_failed_deliveries_are_retried_reindexed_and_forgotten(): void
    {
        [$first, $second, $identity] = [$this->failedDelivery(rkey: 'a'), $this->failedDelivery(rkey: 'b'), $this->failedDelivery('identity')];
        $service = Mockery::mock(FailedDeliveries::class);
        $service->shouldReceive('retry')->once()->withArgs(fn ($delivery) => $delivery->is($first))->andReturn(true);
        $service->shouldReceive('reindex')->once()->withArgs(fn ($delivery) => $delivery->is($second))->andReturn(false);
        $this->app->instance(FailedDeliveries::class, $service);

        Livewire::test(ListFailedDeliveries::class)
            ->callTableAction('retry', $first)
            ->assertNotified('1 went through')
            // The identity event is skipped: it has no record to fetch.
            ->callTableBulkAction('reindex', [$second, $identity])
            ->assertNotified('0 went through')
            ->callTableAction('forget', $identity);

        $this->assertNull($identity->fresh());
    }

    public function test_rejections_and_repairs_are_tallied_under_the_failed_deliveries(): void
    {
        $event = new RecordEvent(1, 'did:plc:alice', self::POSTS, 'a', RecordAction::Create, 'cid', '3l2aaaaaaaaaa', ['text' => 'a'], true);
        app(Rejections::class)->record('App\\Hooks\\RejectSpam', $event, 'looks like spam');
        app(Repairs::class)->record('App\\Repairs\\SlashDates', $event, 3);

        Livewire::test(IngestTallies::class)
            ->assertSee('RejectSpam')
            ->assertSee('looks like spam')
            ->assertSee('SlashDates')
            ->assertSee('repaired')
            ->assertSee('3');
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

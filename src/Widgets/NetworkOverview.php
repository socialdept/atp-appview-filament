<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use SocialDept\AtpAppview\Status\AppviewStatus;
use SocialDept\AtpAppviewFilament\Support\Durations;

/**
 * Is the network flowing into the index: Tap and its consumer, or the
 * Jetstream process, as atp-appview.ingest.source says, plus the label
 * streams when labels come from them.
 */
class NetworkOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '5s';

    protected ?string $heading = 'Network';

    protected function getStats(): array
    {
        $status = app(AppviewStatus::class);
        $sources = $status->sources();
        $labels = $sources['labels'] === 'streams' ? [$this->labelStreamStats($status)] : [];

        if ($sources['records'] === 'jetstream') {
            return [...$this->jetstreamStats($status), ...$labels];
        }

        return [...$this->tapStats($status), ...$labels];
    }

    /**
     * @return array<int, Stat>
     */
    protected function tapStats(AppviewStatus $status): array
    {
        $tap = $status->tap();

        if (! $tap['healthy']) {
            return [
                Stat::make('Tap', 'Unreachable')
                    ->description($tap['error'] ?? 'No answer from its admin API')
                    ->descriptionIcon('heroicon-m-signal-slash')
                    ->color('danger'),
            ];
        }

        $ingest = $status->ingest();
        $written = $status->writes()['fiveMinutes'] / 5;
        $outbox = (int) ($tap['outbox'] ?? 0);
        $drainMinutes = $written > 0 ? $outbox / $written : null;

        return [
            Stat::make('Repos followed', number_format((int) ($tap['repos'] ?? 0)))
                ->description(($ingest['backfilling'] ? 'Backfilling, ' : 'Last event ').Durations::ago($ingest['lastLiveAt'] ?? $ingest['lastBackfillAt']))
                ->descriptionIcon($ingest['backfilling'] ? 'heroicon-m-arrow-path' : 'heroicon-m-check-circle')
                ->color($ingest['backfilling'] ? 'warning' : 'success'),
            Stat::make('Waiting on us', number_format($outbox))
                ->description(match (true) {
                    $outbox === 0 => 'Nothing queued',
                    $drainMinutes === null => 'Not draining',
                    $drainMinutes < 1 => 'Under 1 min to drain',
                    default => sprintf('~%d min to drain', $drainMinutes),
                })
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color($outbox > 10000 ? 'warning' : 'gray'),
            Stat::make('Events a minute', number_format($ingest['perMinute']['live'] + $ingest['perMinute']['backfill']))
                ->description(sprintf('%s rows written', number_format($written)))
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),
            Stat::make('Held during backfill', number_format((int) ($tap['resync'] ?? 0)))
                ->description(($tap['cursor'] ?? null) === null ? 'No cursor yet' : 'Cursor '.Number::abbreviate($tap['cursor'], precision: 2))
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('gray'),
        ];
    }

    /**
     * @return array<int, Stat>
     */
    protected function jetstreamStats(AppviewStatus $status): array
    {
        $jetstream = $status->streams()['jetstream'];

        if (! $jetstream['running']) {
            return [
                Stat::make('Jetstream', 'Not running')
                    ->description($jetstream['seenAt'] === null ? 'Start it with appview:jetstream' : 'Last seen '.Durations::ago($jetstream['seenAt']))
                    ->descriptionIcon('heroicon-m-signal-slash')
                    ->color('danger'),
            ];
        }

        $ingest = $status->ingest();
        $written = $status->writes()['fiveMinutes'] / 5;

        // Jetstream carries account and identity events from the whole
        // network, so a cursor that stops moving is a stream in trouble.
        $stalled = $jetstream['savedAt'] === null || Carbon::parse($jetstream['savedAt'])->lt(Carbon::now()->subMinute());

        return [
            Stat::make('Jetstream', $stalled ? 'Stalled' : 'Following')
                ->description('Cursor saved '.Durations::ago($jetstream['savedAt']))
                ->descriptionIcon($stalled ? 'heroicon-m-pause-circle' : 'heroicon-m-check-circle')
                ->color($stalled ? 'warning' : 'success'),
            Stat::make('Repos followed', number_format($status->accounts()['total']))
                ->description('Last record '.Durations::ago($ingest['lastLiveAt']))
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
            Stat::make('Events a minute', number_format($ingest['perMinute']['live'] + $ingest['perMinute']['identity']))
                ->description(sprintf('%s rows written', number_format($written)))
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info'),
        ];
    }

    protected function labelStreamStats(AppviewStatus $status): Stat
    {
        $labels = $status->streams()['labels'];

        if (! $labels['running']) {
            return Stat::make('Label streams', 'Not running')
                ->description($labels['seenAt'] === null ? 'Start them with appview:labels:stream' : 'Last seen '.Durations::ago($labels['seenAt']))
                ->descriptionIcon('heroicon-m-signal-slash')
                ->color('danger');
        }

        $following = (int) ($labels['following'] ?? 0);

        return Stat::make('Label streams', $following.' open')
            ->description('Last label '.Durations::ago($labels['labelers'][0]['savedAt'] ?? null))
            ->descriptionIcon('heroicon-m-tag')
            ->color($following > 0 ? 'success' : 'gray');
    }
}

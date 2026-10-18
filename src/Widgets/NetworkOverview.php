<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use SocialDept\AtpAppview\Status\AppviewStatus;
use SocialDept\AtpAppviewFilament\Support\Durations;

/**
 * Tap and the consumer: is the network flowing into the index.
 */
class NetworkOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '5s';

    protected ?string $heading = 'Network';

    protected function getStats(): array
    {
        $status = app(AppviewStatus::class);
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
}

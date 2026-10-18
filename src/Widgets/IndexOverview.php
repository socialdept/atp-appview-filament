<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use SocialDept\AtpAppview\Status\AppviewStatus;

/**
 * What the index holds.
 */
class IndexOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = 'Index';

    protected function getStats(): array
    {
        $status = app(AppviewStatus::class);
        $totals = $status->totals();
        $accounts = $status->accounts();
        $storage = $status->storage();
        $inactive = $accounts['inactive'];
        arsort($inactive);
        $topInactive = array_key_first($inactive);
        $rows = max(1, $totals['records'] + $totals['hidden'] + $totals['tombstones']);

        return [
            Stat::make('Records', number_format($totals['records']))
                ->description(sprintf('+%s in 24 h', number_format($status->recentRecords())))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
            Stat::make('Identities', number_format($accounts['total']))
                ->description($topInactive === null ? 'All active' : sprintf('%s %s', number_format($inactive[$topInactive]), $topInactive))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray'),
            Stat::make('Tombstones', number_format($totals['tombstones']))
                ->description(sprintf('+%s hidden', number_format($totals['hidden'])))
                ->descriptionIcon('heroicon-m-eye-slash')
                ->color('gray'),
            Stat::make('On disk', $storage === null ? 'n/a' : Number::fileSize($storage['records'] + $storage['identities'], precision: 1))
                ->description($storage === null ? 'Postgres only' : sprintf('~%s a record', Number::fileSize(intdiv($storage['records'], $rows))))
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('gray'),
        ];
    }
}

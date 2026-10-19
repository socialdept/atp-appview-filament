<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;
use SocialDept\AtpAppview\Ingest\Rejections;
use SocialDept\AtpAppview\Ingest\Repairs;
use SocialDept\AtpAppviewFilament\Support\Links;

/**
 * What the ingest hooks rejected and the ingest repairs rewrote, by class
 * and collection, with the latest record of each.
 */
class IngestTallies extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Rejected and repaired';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => collect([
                ...array_map(fn (array $row) => [...$row, 'outcome' => 'rejected', 'class' => $row['hook'], 'at' => $row['last_rejected_at']], app(Rejections::class)->all()),
                ...array_map(fn (array $row) => [...$row, 'outcome' => 'repaired', 'class' => $row['repair'], 'reason' => null, 'at' => $row['last_repaired_at']], app(Repairs::class)->all()),
            ])->sortByDesc('at')->values()->keyBy(fn (array $row, int $i) => $i)->all())
            ->columns([
                TextColumn::make('class')
                    ->label('Hook or repair')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->tooltip(fn (array $record) => $record['class'])
                    ->weight('medium')
                    ->description(fn (array $record) => $record['reason']),
                TextColumn::make('outcome')
                    ->badge()
                    ->color(fn (string $state) => $state === 'rejected' ? 'danger' : 'warning'),
                TextColumn::make('collection')->fontFamily('mono')->color('gray'),
                TextColumn::make('total')->numeric()->alignEnd(),
                TextColumn::make('at')
                    ->label('Latest')
                    ->formatStateUsing(fn (string $state) => Carbon::parse($state, 'UTC')->diffForHumans(short: true))
                    ->url(fn (array $record) => $record['last_uri'] === null ? null : Links::record($record['last_uri']), shouldOpenInNewTab: true)
                    ->color('gray'),
            ])
            ->emptyStateHeading('Nothing rejected or repaired')
            ->emptyStateDescription('Ingest hooks and repairs are listed in atp-appview.ingest.hooks and atp-appview.ingest.repairs.')
            ->paginated(false);
    }
}

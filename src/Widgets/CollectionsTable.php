<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use SocialDept\AtpAppview\Index\IndexStats;
use SocialDept\AtpAppview\Status\AppviewStatus;
use SocialDept\AtpAppviewFilament\Support\Durations;

/**
 * Records per collection, as appview:stats last counted them.
 */
class CollectionsTable extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Collections';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => collect(app(AppviewStatus::class)->collections())->keyBy('nsid')->all())
            ->columns([
                TextColumn::make('nsid')->label('Collection')->fontFamily('mono'),
                TextColumn::make('records')->numeric()->alignEnd(),
                TextColumn::make('repos')->numeric()->alignEnd(),
                TextColumn::make('hidden')->numeric()->alignEnd()->color('gray'),
                TextColumn::make('tombstones')->numeric()->alignEnd()->color('gray'),
            ])
            ->description(fn () => 'Counted '.Durations::ago(app(AppviewStatus::class)->countedAt()).' by appview:stats')
            ->headerActions([
                Action::make('count')
                    ->label('Count now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        app(IndexStats::class)->refresh();

                        Notification::make()->title('Counted')->success()->send();
                    }),
            ])
            ->paginated(false);
    }
}

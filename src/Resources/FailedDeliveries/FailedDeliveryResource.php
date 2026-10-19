<?php

namespace SocialDept\AtpAppviewFilament\Resources\FailedDeliveries;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use SocialDept\AtpAppview\Ingest\FailedDeliveries;
use SocialDept\AtpAppview\Models\FailedDelivery;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use Throwable;

/**
 * Events the handlers could not take, parked so ingest moved on. Retry once
 * the cause in the app is fixed, reindex once the record is, or forget them.
 */
class FailedDeliveryResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = FailedDelivery::class;

    protected static ?string $slug = 'appview/failed-deliveries';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * How many are parked, so a new failure shows from any page. The table is
     * small and pruned, so counting it is cheap.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = FailedDelivery::query()->count();
        } catch (Throwable) {
            return null;
        }

        return $count > 0 ? number_format($count) : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uri')
                    ->label('Event')
                    ->state(fn (FailedDelivery $record) => $record->uri ?? $record->did)
                    ->fontFamily('mono')
                    ->limit(70)
                    ->description(fn (FailedDelivery $record) => $record->collection ?? 'identity')
                    ->url(fn (FailedDelivery $record) => $record->uri === null ? Links::repo($record->did) : Links::record($record->uri), shouldOpenInNewTab: true),
                TextColumn::make('error')
                    ->limit(80)
                    ->tooltip(fn (FailedDelivery $record) => $record->error)
                    ->description(fn (FailedDelivery $record) => class_basename($record->error_class)),
                TextColumn::make('retries')
                    ->numeric()
                    ->alignEnd()
                    ->color('gray')
                    ->description(fn (FailedDelivery $record) => $record->retried_at === null ? 'not retried' : 'last '.$record->retried_at->diffForHumans(short: true)),
                TextColumn::make('failed_at')->label('Failed')->since()->sortable()->color('gray'),
            ])
            ->defaultSort('failed_at', 'desc')
            ->filters([
                SelectFilter::make('collection')
                    ->options(fn () => FailedDelivery::query()->whereNotNull('collection')->distinct()->orderBy('collection')->pluck('collection', 'collection')->all()),
                SelectFilter::make('kind')
                    ->options(['record' => 'Records', 'identity' => 'Identity events']),
            ])
            ->emptyStateHeading('No failed deliveries')
            ->emptyStateDescription('Events the handlers cannot take are parked here, so the rest of their batch goes through.')
            ->recordActions([
                Action::make('payload')
                    ->iconButton()
                    ->tooltip('Event')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(fn (FailedDelivery $record) => $record->uri ?? $record->did)
                    ->modalWidth('4xl')
                    ->modalContent(fn (FailedDelivery $record) => Json::render([
                        'error' => $record->error,
                        'errorClass' => $record->error_class,
                        'failedAt' => $record->failed_at?->toIso8601ZuluString(),
                        'retries' => $record->retries,
                        'event' => $record->payload,
                    ], $record->did))
                    ->modalSubmitAction(false),
                Action::make('retry')
                    ->iconButton()
                    ->tooltip('Retry: run the parked event through ingest again')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (FailedDelivery $record) => self::report('retry', [$record])),
                Action::make('reindex')
                    ->iconButton()
                    ->tooltip('Reindex: fetch the record from its PDS as it is now')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->visible(fn (FailedDelivery $record) => $record->kind === 'record')
                    ->action(fn (FailedDelivery $record) => self::report('reindex', [$record])),
                Action::make('forget')
                    ->iconButton()
                    ->tooltip('Forget')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Drop this failed delivery without indexing it. The record stays out of the index until its author changes it or the repo is resynced.')
                    ->action(function (FailedDelivery $record) {
                        $record->delete();

                        Notification::make()->title('Forgotten')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('retry')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Collection $records) => self::report('retry', $records->all()))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('reindex')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->action(fn (Collection $records) => self::report('reindex', $records->all()))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('forget')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Drop these failed deliveries without indexing them.')
                    ->action(function (Collection $records) {
                        $count = FailedDelivery::query()->whereKey($records->modelKeys())->delete();

                        Notification::make()->title(number_format($count).' forgotten')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Retry or reindex each delivery and say how many went through. Identity
     * events have no record to fetch, so reindexing skips them.
     *
     * @param  'retry'|'reindex'  $how
     * @param  array<int, FailedDelivery>  $deliveries
     */
    public static function report(string $how, array $deliveries): void
    {
        $service = app(FailedDeliveries::class);
        $done = 0;
        $failed = [];
        $skipped = 0;

        foreach ($deliveries as $delivery) {
            if ($how === 'reindex' && $delivery->kind !== 'record') {
                $skipped++;

                continue;
            }

            ($how === 'reindex' ? $service->reindex($delivery) : $service->retry($delivery))
                ? $done++
                : $failed[] = $delivery;
        }

        $body = collect([
            $failed === [] ? null : count($failed).' failed again: '.str($failed[0]->fresh()?->error ?? '')->limit(160),
            $skipped > 0 ? "{$skipped} identity ".str('event')->plural($skipped).' skipped, retry those instead' : null,
        ])->filter()->implode('. ');

        Notification::make()
            ->title(number_format($done).' went through')
            ->body($body ?: null)
            ->{$failed === [] ? 'success' : 'warning'}()
            ->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFailedDeliveries::route('/')];
    }
}

<?php

namespace SocialDept\AtpAppviewFilament\Resources\Identities;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use SocialDept\AtpAppview\Index\ReconcileErrors;
use SocialDept\AtpAppview\Index\Reconciler;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppviewFilament\AtpAppviewPlugin;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Support\Avatars;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use Throwable;

/**
 * Every repo the index knows: its handle and account status, and what the
 * reconciler last found.
 */
class IdentityResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = Identity::class;

    protected static ?string $modelLabel = 'identity';

    protected static ?string $slug = 'appview/identities';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'handle';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Identity $record, HasTable $livewire) => app(Avatars::class)->of($record->did, fn () => $livewire->getTableRecords()->pluck('did')->all()))
                    ->defaultImageUrl(fn (Identity $record) => Avatars::placeholder($record->handle))
                    ->circular()
                    ->imageSize(32)
                    ->grow(false),
                TextColumn::make('handle')
                    ->formatStateUsing(fn (?string $state) => $state === null ? null : '@'.$state)
                    ->limit(40)
                    ->placeholder('No handle')
                    ->description(fn (Identity $record) => $record->did)
                    ->searchable(['handle', 'did'])
                    ->weight('medium'),
                TextColumn::make('pds')
                    ->label('PDS')
                    ->formatStateUsing(fn (?string $state) => $state === null ? null : parse_url($state, PHP_URL_HOST))
                    ->placeholder('Unresolved')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->state(fn (Identity $record) => $record->active ? 'active' : ($record->status ?? 'inactive'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'deleted' => 'danger',
                        'takendown', 'suspended' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('records_count')
                    ->label('Records')
                    ->state(fn (Identity $record) => $record->records_count)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('reconciled_at')
                    ->label('Reconciled')
                    ->since()
                    ->placeholder('Not yet')
                    ->sortable()
                    ->color('gray'),
                TextColumn::make('reconcile_error')
                    ->label('Last check')
                    ->state(fn (Identity $record) => $record->reconcile_error === null ? ($record->reconciled_at ? 'ok' : null) : ReconcileErrors::kind($record->reconcile_error))
                    ->badge()
                    ->color(fn (?string $state) => $state === 'ok' ? 'success' : 'danger')
                    ->tooltip(fn (Identity $record) => $record->reconcile_error),
            ])
            ->recordUrl(fn (Identity $record) => self::getUrl('view', ['record' => $record]))
            // Never-reconciled repos last, on any database.
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('reconciled_at is null')->orderByDesc('reconciled_at'))
            ->filters([
                SelectFilter::make('status')
                    ->options(['active' => 'Active', 'deactivated' => 'Deactivated', 'takendown' => 'Taken down', 'suspended' => 'Suspended', 'deleted' => 'Deleted'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'active' => $query->where('active', true),
                        default => $query->where('active', false)->where('status', $data['value']),
                    }),
                TernaryFilter::make('reconcile_error')
                    ->label('Reconciliation')
                    ->trueLabel('Failed last check')
                    ->falseLabel('Passed last check')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('reconcile_error'),
                        false: fn (Builder $query) => $query->whereNotNull('reconciled_at')->whereNull('reconcile_error'),
                    ),
                Filter::make('never_reconciled')->label('Never reconciled')->query(fn (Builder $query) => $query->whereNull('reconciled_at')),
            ])
            ->recordActions([
                Action::make('open')
                    ->iconButton()
                    ->tooltip('Open repo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Identity $record) => Links::repo($record->did), shouldOpenInNewTab: true),
                Action::make('records')
                    ->iconButton()
                    ->tooltip('Records')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->visible(fn () => self::recordsPage())
                    ->url(fn (Identity $record) => RecordResource::getUrl('index', ['filters' => ['did' => ['value' => $record->did]]])),
                Action::make('reconcile')
                    ->iconButton()
                    ->tooltip('Reconcile now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Identity $record) => self::reconcile($record->did)),
                Action::make('error')
                    ->iconButton()
                    ->tooltip('Last error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (Identity $record) => $record->reconcile_error !== null)
                    ->modalHeading(fn (Identity $record) => ReconcileErrors::kind((string) $record->reconcile_error))
                    ->modalContent(fn (Identity $record) => Json::render(['did' => $record->did, 'pds' => $record->pds, 'error' => $record->reconcile_error, 'checked' => $record->reconciled_at?->toIso8601String()]))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([
                BulkAction::make('reconcileSelected')
                    ->label('Reconcile selected')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Collection $records) {
                        $ok = $records->filter(function (Identity $record) {
                            try {
                                app(Reconciler::class)->reconcileAndRecord($record->did);

                                return true;
                            } catch (Throwable) {
                                return false;
                            }
                        })->count();

                        Notification::make()->title("Reconciled {$ok} of {$records->count()} repos")->success()->send();
                    }),
            ]);
    }

    /**
     * Reconcile one repo now and say what changed, as appview:reconcile would
     * record it.
     */
    public static function reconcile(string $did): void
    {
        try {
            $result = app(Reconciler::class)->reconcileAndRecord($did);
        } catch (Throwable $e) {
            Notification::make()->title('Could not reconcile')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Reconciled')->body($result->summary())->success()->send();
    }

    /**
     * The identity's page in the panel, when the plugin has one, otherwise
     * where recordLinks() sends a DID.
     */
    public static function linkTo(string $did, bool $known = true): string
    {
        try {
            $page = $known && AtpAppviewPlugin::get()->hasIdentities();
        } catch (Throwable) {
            $page = false;
        }

        return $page ? self::getUrl('view', ['record' => $did]) : Links::repo($did);
    }

    public static function recordsPage(): bool
    {
        try {
            return AtpAppviewPlugin::get()->hasRecords();
        } catch (Throwable) {
            return false;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIdentities::route('/'),
            'view' => Pages\ViewIdentity::route('/{record}'),
        ];
    }
}

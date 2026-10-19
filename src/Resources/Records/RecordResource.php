<?php

namespace SocialDept\AtpAppviewFilament\Resources\Records;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SocialDept\AtpAppview\Ingest\Collections;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;

/**
 * The index, a collection at a time: the indexes sort within a collection,
 * so a collection is always chosen.
 */
class RecordResource extends Resource
{
    use InPluginNavigationGroup;

    /**
     * Record fields tried in order for a row's title, before its rkey.
     */
    public const TITLE_FIELDS = ['title', 'name', 'displayName', 'text', 'status'];

    protected static ?string $model = Record::class;

    protected static ?string $slug = 'appview/records';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        // Followed and companion collections alike, such as profiles.
        $collections = Collections::all();
        $collections = array_combine($collections, $collections);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('identity')->withHidden())
            ->columns([
                TextColumn::make('title')
                    ->state(fn (Record $record) => self::title($record))
                    ->limit(60)
                    ->weight('medium')
                    ->description(fn (Record $record) => $record->collection),
                TextColumn::make('identity.handle')
                    ->label('Repo')
                    ->formatStateUsing(fn (?string $state) => $state === null ? null : '@'.$state)
                    ->placeholder(fn (Record $record) => $record->did)
                    // The identity's page when the index knows it, the repo elsewhere otherwise.
                    ->url(fn (Record $record) => IdentityResource::linkTo($record->did, $record->identity !== null))
                    ->openUrlInNewTab(fn (Record $record) => $record->identity === null),
                TextColumn::make('state')
                    ->state(fn (Record $record) => match (true) {
                        $record->deleted_at !== null => 'tombstone',
                        $record->hidden_at !== null => 'hidden',
                        default => null,
                    })
                    ->badge()
                    ->color(fn (?string $state) => $state === 'tombstone' ? 'danger' : 'warning'),
                TextColumn::make('sort_at')->label('Made')->since()->sortable()->color('gray'),
                TextColumn::make('indexed_at')->label('Indexed')->since()->color('gray'),
            ])
            ->defaultSort('sort_at', 'desc')
            ->filters([
                SelectFilter::make('collection')
                    ->options($collections)
                    ->default(array_key_first($collections))
                    ->selectablePlaceholder(false),
                Filter::make('did')
                    ->schema([TextInput::make('value')->label('Repo DID')->placeholder('did:plc:...')])
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->where('did', $data['value']) : $query)
                    ->indicateUsing(fn (array $data) => filled($data['value'] ?? null) ? 'Repo: '.$data['value'] : null),
                TernaryFilter::make('trashed')
                    ->label('Tombstones')
                    ->placeholder('Without tombstones')
                    ->trueLabel('With tombstones')
                    ->falseLabel('Only tombstones')
                    ->queries(
                        true: fn (Builder $query) => $query->withTrashed(),
                        false: fn (Builder $query) => $query->onlyTrashed(),
                        blank: fn (Builder $query) => $query,
                    ),
                TernaryFilter::make('hidden')
                    ->label('Hidden')
                    ->placeholder('With hidden')
                    ->trueLabel('Only hidden')
                    ->falseLabel('Without hidden')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('hidden_at'),
                        false: fn (Builder $query) => $query->whereNull('hidden_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                Action::make('json')
                    ->iconButton()
                    ->tooltip('Record')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(fn (Record $record) => $record->uri)
                    ->modalWidth('4xl')
                    ->modalContent(fn (Record $record) => Json::render([
                        'uri' => $record->uri,
                        'cid' => $record->cid,
                        'rev' => $record->rev,
                        'sortAt' => $record->sort_at?->toIso8601ZuluString(),
                        'indexedAt' => $record->indexed_at?->toIso8601ZuluString(),
                        'value' => $record->record,
                    ], $record->did, $record->identity?->pds))
                    ->modalSubmitAction(false),
                Action::make('open')
                    ->iconButton()
                    ->tooltip('Open record')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Record $record) => Links::record($record->uri), shouldOpenInNewTab: true),
                Action::make('reconcile')
                    ->iconButton()
                    ->tooltip('Reconcile repo')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (Record $record) => IdentityResource::reconcile($record->did)),
            ])
            ->searchable(false)
            // Simple pagination skips the count(*) a numbered paginator runs on
            // every render. The dashboard already shows each collection's total.
            ->paginationMode(PaginationMode::Simple);
    }

    public static function title(Record $record): string
    {
        foreach (self::TITLE_FIELDS as $field) {
            $value = $record->record[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $record->rkey;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRecords::route('/')];
    }
}

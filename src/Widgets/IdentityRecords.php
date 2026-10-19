<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use SocialDept\AtpAppviewFilament\Support\RecordLabels;

/**
 * One repo's records, newest first, across its collections. Read through
 * the records table's (did, collection) index.
 */
class IdentityRecords extends TableWidget
{
    public string $did;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Records';

    /**
     * A collection chosen on the profile, shown alone.
     */
    #[On('atp-identity-collection')]
    public function showCollection(?string $collection = null): void
    {
        $this->tableFilters['collection']['value'] = $collection;
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Record::query()->withHidden()->where('did', $this->did))
            ->columns([
                TextColumn::make('title')
                    ->state(fn (Record $record) => RecordResource::title($record))
                    ->limit(80)
                    ->weight('medium')
                    ->description(fn (Record $record) => $record->collection),
                TextColumn::make('state')
                    ->state(fn (Record $record) => match (true) {
                        $record->deleted_at !== null => 'tombstone',
                        $record->hidden_at !== null => 'hidden',
                        default => null,
                    })
                    ->badge()
                    ->color(fn (?string $state) => $state === 'tombstone' ? 'danger' : 'warning'),
                RecordLabels::column(),
                TextColumn::make('sort_at')->label('Made')->since()->sortable()->color('gray'),
                TextColumn::make('indexed_at')->label('Indexed')->since()->color('gray'),
            ])
            ->defaultSort('sort_at', 'desc')
            ->filters([
                SelectFilter::make('collection')
                    ->options(fn () => Record::query()->withHidden()->where('did', $this->did)->distinct()->orderBy('collection')->pluck('collection', 'collection')->all()),
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
                        ...($record->repair === null ? [] : ['repair' => $record->repair]),
                    ], $record->did))
                    ->modalSubmitAction(false),
                Action::make('open')
                    ->iconButton()
                    ->tooltip('Open record')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Record $record) => Links::record($record->uri), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('No records indexed')
            ->searchable(false)
            ->paginationMode(PaginationMode::Simple);
    }
}

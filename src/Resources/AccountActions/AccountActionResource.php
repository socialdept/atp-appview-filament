<?php

namespace SocialDept\AtpAppviewFilament\Resources\AccountActions;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use SocialDept\AtpAppview\Models\AccountAction;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;

/**
 * Every ban, review and takedown, by an operator or a label, and when each
 * was lifted. The record of moderation.
 */
class AccountActionResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = AccountAction::class;

    protected static ?string $slug = 'appview/moderation';

    protected static ?string $navigationLabel = 'Moderation log';

    protected static ?string $modelLabel = 'action';

    protected static ?string $pluralModelLabel = 'moderation log';

    protected static ?string $breadcrumb = 'Moderation log';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('did')
                    ->label('Account')
                    ->state(fn (AccountAction $record) => ($handle = Identity::query()->whereKey($record->did)->value('handle')) ? '@'.$handle : $record->did)
                    ->description(fn (AccountAction $record) => $record->did)
                    ->url(fn (AccountAction $record) => IdentityResource::linkTo($record->did, Identity::query()->whereKey($record->did)->exists()))
                    ->searchable(['did'])
                    ->weight('medium'),
                TextColumn::make('action')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        AccountAction::BAN => 'Ban',
                        AccountAction::REVIEW => 'Needs review',
                        AccountAction::TAKEDOWN => 'Takedown',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === AccountAction::REVIEW ? 'warning' : 'danger'),
                TextColumn::make('source')
                    ->state(fn (AccountAction $record) => $record->source === AccountAction::LABEL ? "label {$record->label_val}" : 'manual')
                    ->description(fn (AccountAction $record) => $record->source === AccountAction::LABEL ? $record->label_src : $record->actor)
                    ->color('gray'),
                TextColumn::make('reason')->limit(60)->tooltip(fn (AccountAction $record) => $record->reason)->placeholder('No reason given'),
                TextColumn::make('created_at')->label('When')->since()->sortable()->color('gray'),
                TextColumn::make('lifted_at')
                    ->label('Lifted')
                    ->since()
                    ->placeholder('In force')
                    ->description(fn (AccountAction $record) => $record->lifted_by)
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->options([AccountAction::BAN => 'Ban', AccountAction::REVIEW => 'Needs review', AccountAction::TAKEDOWN => 'Takedown']),
                SelectFilter::make('source')->options([AccountAction::MANUAL => 'Manual', AccountAction::LABEL => 'Label']),
                TernaryFilter::make('lifted')
                    ->label('State')
                    ->placeholder('All')
                    ->trueLabel('Lifted')
                    ->falseLabel('In force')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('lifted_at'),
                        false: fn (Builder $query) => $query->whereNull('lifted_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->emptyStateHeading('Nothing moderated yet')
            ->emptyStateDescription('Bans, reviews and takedowns appear here, whether an operator or a label applied them.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAccountActions::route('/')];
    }
}

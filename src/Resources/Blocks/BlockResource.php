<?php

namespace SocialDept\AtpAppviewFilament\Resources\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use SocialDept\AtpAppview\Blocklist\BlockKind;
use SocialDept\AtpAppview\Blocklist\Blocklist;
use SocialDept\AtpAppview\Blocklist\BlockOutcome;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use Throwable;

/**
 * Rules that keep a repo, or every repo on a PDS host, out of the index.
 */
class BlockResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = Block::class;

    protected static ?string $modelLabel = 'block';

    protected static ?string $pluralModelLabel = 'blocklist';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $slug = 'appview/blocklist';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->description(fn (Block $record) => $record->note)
                    ->searchable(),
                TextColumn::make('kind')
                    ->formatStateUsing(fn (BlockKind $state) => $state === BlockKind::Did ? 'repo' : 'host')
                    ->badge()
                    ->color(fn (BlockKind $state) => $state === BlockKind::Did ? 'gray' : 'warning'),
                TextColumn::make('collection')
                    ->formatStateUsing(fn (string $state) => $state === Block::EVERY_COLLECTION ? 'every collection' : $state)
                    ->fontFamily(fn (string $state) => $state === Block::EVERY_COLLECTION ? null : 'mono')
                    ->color('gray'),
                TextColumn::make('blocked_count')
                    ->label('Dropped')
                    ->numeric()
                    ->alignEnd()
                    ->description(fn (Block $record) => $record->last_blocked_at === null ? 'none yet' : 'last '.$record->last_blocked_at->diffForHumans(short: true)),
                TextColumn::make('created_at')->label('Added')->since()->sortable()->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nothing blocked')
            ->emptyStateDescription('Block a repo by DID, or every repo on a PDS host with a pattern such as *.spam.example.')
            ->headerActions([
                Action::make('block')
                    ->label('Block')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->modalWidth('lg')
                    ->schema([
                        TextInput::make('subject')
                            ->label('Repo or host')
                            ->placeholder('did:plc:... or *.spam.example')
                            ->helperText('A DID blocks one repo. A host or pattern blocks every repo on matching PDSes.')
                            ->required(),
                        TextInput::make('collection')
                            ->placeholder('Every collection')
                            ->helperText('An NSID, or a prefix ending in .*'),
                        Textarea::make('note')->rows(2),
                        Toggle::make('purge')
                            ->label('Delete what is already indexed')
                            ->helperText('Otherwise only new records are dropped.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $outcome = app(Blocklist::class)->block($data['subject'], $data['collection'] ?: null, $data['note'] ?: null, (bool) $data['purge']);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not block')->body(e($e->getMessage()))->danger()->send();

                            return;
                        }

                        self::report('Blocked '.$outcome->block->subject, $outcome);
                    }),
            ])
            ->recordActions([
                Action::make('purge')
                    ->iconButton()
                    ->tooltip('Purge indexed records')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Delete every indexed record this rule covers. Records arriving later are dropped at ingest either way.')
                    ->action(function (Block $record) {
                        $purged = app(Blocklist::class)->purge($record);

                        Notification::make()->title(e(number_format($purged).' '.str('record')->plural($purged).' deleted'))->success()->send();
                    }),
                Action::make('unblock')
                    ->iconButton()
                    ->tooltip('Unblock')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Records from here are indexed again as they arrive, and Tap is asked to backfill blocked repos.')
                    ->action(function (Block $record) {
                        $outcome = app(Blocklist::class)->unblock($record->subject, $record->collection);

                        $outcome === null
                            ? Notification::make()->title('Already unblocked')->send()
                            : self::report('Unblocked '.$record->subject, $outcome);
                    }),
            ]);
    }

    /**
     * Say what a block or unblock did, and whether Tap was told.
     */
    public static function report(string $title, BlockOutcome $outcome): void
    {
        $body = collect([
            $outcome->purged > 0 ? number_format($outcome->purged).' records deleted' : null,
            $outcome->removedFromTap !== [] ? count($outcome->removedFromTap).' repos dropped from Tap' : null,
            $outcome->resyncedInTap !== [] ? count($outcome->resyncedInTap).' repos backfilling again' : null,
        ])->filter()->implode(', ');

        $outcome->tapError === null
            ? Notification::make()->title(e($title))->body(e($body ?: null))->success()->send()
            : Notification::make()->title(e($title))->body(e("Tap was not updated: {$outcome->tapError}. The rule still applies at ingest."))->warning()->send();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBlocks::route('/')];
    }
}

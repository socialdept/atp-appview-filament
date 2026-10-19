<?php

namespace SocialDept\AtpAppviewFilament\Resources\Labelers;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use SocialDept\AtpAppview\Labels\Labels;
use SocialDept\AtpAppview\Labels\Tack;
use SocialDept\AtpAppview\Models\Label;
use SocialDept\AtpAppview\Models\Labeler;
use SocialDept\AtpAppview\Profiles\Profiles;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Support\Avatars;
use SocialDept\AtpSupport\Facades\Resolver;
use Throwable;

/**
 * The labelers Tack follows: their profile, how Tack's connection to each
 * is doing, and what their labels do.
 */
class LabelerResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = Labeler::class;

    protected static ?string $slug = 'appview/labelers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'did';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * Tack's view of each labeler, by DID, read at most every 15 seconds.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function tackState(): array
    {
        if (! Tack::configured()) {
            return [];
        }

        return Cache::remember('atp-appview-filament:tack-labelers', 15, function () {
            try {
                return collect(app(Tack::class)->labelers())->keyBy('did')->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * Labelers' profiles, for their names and avatars.
     *
     * @param  array<int, string>  $dids
     * @return array<string, array<string, mixed>>
     */
    public static function profiles(array $dids): array
    {
        try {
            return app(Profiles::class)->basic($dids);
        } catch (Throwable) {
            return [];
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (Labeler $record, HasTable $livewire) => self::profiles($livewire->getTableRecords()->pluck('did')->all())[$record->did]['avatar'] ?? null)
                    ->defaultImageUrl(fn (Labeler $record) => Avatars::placeholder(self::profiles([$record->did])[$record->did]['handle'] ?? null))
                    ->circular()
                    ->imageSize(32)
                    ->grow(false),
                TextColumn::make('name')
                    ->state(function (Labeler $record) {
                        $profile = self::profiles([$record->did])[$record->did] ?? [];

                        return $profile['displayName'] ?? $profile['handle'] ?? $record->did;
                    })
                    ->description(fn (Labeler $record) => $record->did)
                    ->weight('medium'),
                TextColumn::make('status')
                    ->state(fn (Labeler $record) => self::tackState()[$record->did]['status'] ?? (Tack::configured() ? 'unknown' : 'no tack'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'connected' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Labeler $record) => self::tackState()[$record->did]['lastError'] ?? null),
                TextColumn::make('values')
                    ->state(fn (Labeler $record) => count($record->values()))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('labels')
                    ->label('Labels kept')
                    ->state(fn (Labeler $record) => Label::query()->where('src', $record->did)->count())
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('received')
                    ->state(fn (Labeler $record) => self::tackState()[$record->did]['received'] ?? null)
                    ->numeric()
                    ->alignEnd()
                    ->color('gray')
                    ->description(fn (Labeler $record) => ($rejected = self::tackState()[$record->did]['rejected'] ?? 0) > 0 ? number_format($rejected).' rejected' : null),
            ])
            ->recordUrl(fn (Labeler $record) => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No labelers')
            ->emptyStateDescription(Tack::configured()
                ? 'Tack sends each labeler it follows. Run appview:labels:sync to read them.'
                : 'Set atp-appview.labels.tack.url to follow labelers through Tack.')
            ->headerActions([
                Action::make('follow')
                    ->label('Follow labeler')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => Tack::configured())
                    ->modalWidth('md')
                    ->schema([
                        TextInput::make('labeler')
                            ->label('Handle or DID')
                            ->placeholder('moderation.example.com')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        try {
                            $did = str_starts_with($data['labeler'], 'did:') ? $data['labeler'] : Resolver::handleToDid(ltrim($data['labeler'], '@'));
                            app(Labels::class)->follow($did);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not follow it')->body(e($e->getMessage()))->danger()->send();

                            return;
                        }

                        Cache::forget('atp-appview-filament:tack-labelers');
                        Notification::make()->title('Following')->body(e("Tack is subscribing to {$did}. Its values arrive with its declaration."))->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('open')
                    ->iconButton()
                    ->tooltip('Open profile')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Labeler $record) => 'https://bsky.app/profile/'.$record->did, shouldOpenInNewTab: true),
                Action::make('unfollow')
                    ->iconButton()
                    ->tooltip('Stop following')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn () => Tack::configured())
                    ->requiresConfirmation()
                    ->modalDescription('Tack stops following this labeler, and its labels are dropped, along with anything they hide or flag.')
                    ->action(function (Labeler $record) {
                        try {
                            app(Labels::class)->unfollow($record->did);
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not stop following it')->body(e($e->getMessage()))->danger()->send();

                            return;
                        }

                        Cache::forget('atp-appview-filament:tack-labelers');
                        Notification::make()->title('No longer following')->success()->send();
                    }),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelers::route('/'),
            'view' => Pages\ViewLabeler::route('/{record}'),
        ];
    }
}

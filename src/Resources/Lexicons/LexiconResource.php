<?php

namespace SocialDept\AtpAppviewFilament\Resources\Lexicons;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use SocialDept\AtpAppview\Lexicons\FollowResult;
use SocialDept\AtpAppview\Lexicons\LexiconFollower;
use SocialDept\AtpAppview\Models\FollowedLexicon;
use SocialDept\AtpAppviewFilament\Resources\Concerns\InPluginNavigationGroup;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use Throwable;

/**
 * Lexicons followed from the network, the revision in use, and any new
 * version held back for breaking the one before.
 */
class LexiconResource extends Resource
{
    use InPluginNavigationGroup;

    protected static ?string $model = FollowedLexicon::class;

    protected static ?string $modelLabel = 'lexicon';

    protected static ?string $slug = 'appview/lexicons';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $held = FollowedLexicon::query()->whereIn('nsid', LexiconFollower::followed())->whereNotNull('pending_schema')->count();

        return $held > 0 ? (string) $held : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('nsid', LexiconFollower::followed()))
            ->columns([
                TextColumn::make('nsid')
                    ->label('NSID')
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->description(fn (FollowedLexicon $record) => str($record->schema['defs']['main']['description'] ?? $record->schema['description'] ?? '')->limit(70)->toString() ?: null)
                    ->searchable(),
                TextColumn::make('revision')
                    ->formatStateUsing(fn (int $state) => 'r'.$state)
                    ->badge()
                    ->color('gray'),
                TextColumn::make('state')
                    ->state(fn (FollowedLexicon $record) => $record->heldBack() ? 'held back' : 'in use')
                    ->badge()
                    ->color(fn (string $state) => $state === 'held back' ? 'danger' : 'success')
                    ->description(fn (FollowedLexicon $record) => $record->heldBack() ? count($record->pending_issues ?? []).' breaking '.str('change')->plural(count($record->pending_issues ?? [])) : null),
                TextColumn::make('did')
                    ->label('Authority')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray')
                    ->url(fn (FollowedLexicon $record) => Links::record($record->uri), shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fetched_at')
                    ->label('Checked')
                    ->since()
                    ->color('gray')
                    ->description(fn (FollowedLexicon $record) => $record->updated_at === null ? null : 'changed '.$record->updated_at->diffForHumans(short: true)),
            ])
            ->defaultSort('nsid')
            ->paginated(false)
            ->emptyStateHeading('No lexicons followed')
            ->emptyStateDescription('List published lexicons in atp-appview.lexicons.follow, then check for them here or run appview:lexicons:sync.')
            ->recordActions([
                Action::make('schema')
                    ->iconButton()
                    ->tooltip('Schema')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading(fn (FollowedLexicon $record) => $record->nsid.' r'.$record->revision)
                    ->modalWidth('4xl')
                    ->modalContent(fn (FollowedLexicon $record) => Json::render($record->schema))
                    ->modalSubmitAction(false),
                Action::make('review')
                    ->iconButton()
                    ->tooltip('Review held-back version')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (FollowedLexicon $record) => $record->heldBack())
                    ->modalHeading(fn (FollowedLexicon $record) => 'A new '.$record->nsid.' breaks r'.$record->revision)
                    ->modalDescription('Accepting it replaces the schema records are validated against. Records written to the old shape may start failing validation.')
                    ->modalContent(fn (FollowedLexicon $record) => self::issues($record))
                    ->modalWidth('3xl')
                    ->modalSubmitActionLabel('Accept anyway')
                    ->action(function (FollowedLexicon $record) {
                        $revision = app(LexiconFollower::class)->accept($record->nsid);

                        Notification::make()->title("{$record->nsid} is now r{$revision}")->success()->send();
                    }),
                Action::make('sync')
                    ->iconButton()
                    ->tooltip('Check for a new version')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn (FollowedLexicon $record) => self::sync([$record->nsid])),
            ])
            ->headerActions([
                Action::make('syncAll')
                    ->label('Check all')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => LexiconFollower::followed() !== [])
                    ->action(fn () => self::sync(LexiconFollower::followed())),
            ]);
    }

    /**
     * The breaking changes that held a version back, then the version itself.
     */
    public static function issues(FollowedLexicon $record): HtmlString
    {
        $items = collect($record->pending_issues ?? [])
            ->map(fn (array $issue) => '<li class="atp-issue">'
                .'<span class="atp-issue-name">'.e($issue['name'] ?? 'change').'</span>'
                .'<span>'.e($issue['message'] ?? '').'</span></li>')
            ->implode('');

        return new HtmlString('<ul class="atp-issues">'.$items.'</ul>'.Json::render($record->pending_schema)->toHtml());
    }

    /**
     * @param  array<int, string>  $nsids
     */
    protected static function sync(array $nsids): void
    {
        $follower = app(LexiconFollower::class);
        $outcomes = [];

        foreach ($nsids as $nsid) {
            try {
                $outcomes[] = $follower->sync($nsid);
            } catch (Throwable $e) {
                Notification::make()->title("Could not check {$nsid}")->body($e->getMessage())->danger()->send();
            }
        }

        $changed = collect($outcomes)->reject(fn (FollowResult $result) => $result->outcome === FollowResult::UNCHANGED);

        if ($changed->isEmpty()) {
            Notification::make()->title(count($outcomes) === 1 ? 'No new version' : 'No new versions')->success()->send();

            return;
        }

        foreach ($changed as $result) {
            Notification::make()
                ->title("{$result->nsid}: {$result->outcome}")
                ->body($result->revision === null ? null : 'r'.$result->revision)
                ->color($result->outcome === FollowResult::REFUSED ? 'danger' : 'success')
                ->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLexicons::route('/')];
    }
}

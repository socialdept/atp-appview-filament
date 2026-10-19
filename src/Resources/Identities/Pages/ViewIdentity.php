<?php

namespace SocialDept\AtpAppviewFilament\Resources\Identities\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use SocialDept\AtpAppview\Blocklist\Blocklist;
use SocialDept\AtpAppview\Index\IdentityRefresh;
use SocialDept\AtpAppview\Index\Reconciler;
use SocialDept\AtpAppview\Index\ReconcileResult;
use SocialDept\AtpAppview\Ingest\TapAdmin;
use SocialDept\AtpAppview\Ingest\TapFeatureUnavailableException;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\FailedDelivery;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppview\Moderation\AccountActions;
use SocialDept\AtpAppviewFilament\Resources\Blocks\BlockResource;
use SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\FailedDeliveryResource;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Resources\Records\RecordResource;
use SocialDept\AtpAppviewFilament\Support\IdentityProfile;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use SocialDept\AtpAppviewFilament\Widgets\IdentityRecords;
use Throwable;

/**
 * One repo as a social profile: banner, avatar, name, bio and what the index
 * holds for it, then its records.
 *
 * @property Identity $record
 */
class ViewIdentity extends ViewRecord
{
    protected static string $resource = IdentityResource::class;

    protected ?IdentityProfile $profile = null;

    /**
     * The display name, or the handle when there is none, as on the profile.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->profile()->name();
    }

    /**
     * The profile is the page's header, so Filament's own heading is left out.
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    /**
     * Identities, then the profile's name, without the trailing "View".
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            IdentityResource::getUrl('index') => IdentityResource::getBreadcrumb(),
            $this->profile()->name(),
        ];
    }

    /** @var array{result: ReconcileResult|null, error: string|null}|null */
    protected ?array $preview = null;

    protected ?int $failures = null;

    /**
     * What reconciling would change, read once per request.
     *
     * @return array{result: ReconcileResult|null, error: string|null}
     */
    protected function reconcilePreview(): array
    {
        if ($this->preview !== null) {
            return $this->preview;
        }

        try {
            return $this->preview = ['result' => app(Reconciler::class)->reconcile($this->record->did, dryRun: true), 'error' => null];
        } catch (Throwable $e) {
            return $this->preview = ['result' => null, 'error' => $e->getMessage()];
        }
    }

    protected function failures(): int
    {
        return $this->failures ??= FailedDelivery::query()->where('did', $this->record->did)->count();
    }

    protected function refreshIdentity(): void
    {
        try {
            $result = app(IdentityRefresh::class)->refresh($this->record->did);
        } catch (Throwable $e) {
            Notification::make()->title('Could not read the DID document')->body(e($e->getMessage()))->danger()->send();

            return;
        }

        $changes = collect($result['changed'])->map(fn (array $change, string $field) => "{$field}: ".($change[0] ?? 'none').' to '.($change[1] ?? 'none'))->implode(', ');

        Notification::make()
            ->title('Identity refreshed')
            ->body(e($changes !== '' ? $changes : 'Handle and PDS unchanged. The cached profile was dropped.'))
            ->success()
            ->send();

        $this->record->refresh();
        $this->profile = null;
    }

    protected function resync(): void
    {
        try {
            $state = app(TapAdmin::class)->resyncRepo($this->record->did);
        } catch (TapFeatureUnavailableException) {
            Notification::make()->title('This Tap cannot resync')->body('Resyncing needs a Tap with POST /resync/:did, such as Tab.')->warning()->send();

            return;
        } catch (Throwable $e) {
            Notification::make()->title('Tap did not answer')->body(e($e->getMessage()))->danger()->send();

            return;
        }

        $state === null
            ? Notification::make()->title('Tap does not track this repo')->warning()->send()
            : Notification::make()->title('Resync queued')->body(e("Tap reports the repo as {$state}."))->success()->send();
    }

    /**
     * Run an account action, report it, and show the account as it is now.
     *
     * @param  \Closure(AccountActions): mixed  $run
     */
    protected function act(\Closure $run, string $done): void
    {
        try {
            $run(app(AccountActions::class));
        } catch (Throwable $e) {
            Notification::make()->title('Could not do that')->body(e($e->getMessage()))->danger()->send();

            return;
        }

        Notification::make()->title(e($done.' '.$this->profile()->name()))->success()->send();
        $this->record->refresh();
        $this->profile = null;
    }

    /**
     * Who is acting, for the moderation log: the operator's DID, or their id.
     */
    protected function actor(): ?string
    {
        $user = auth()->user();

        return $user === null ? null : (string) ($user->did ?? $user->getAuthIdentifier());
    }

    protected function profile(): IdentityProfile
    {
        return $this->profile ??= IdentityProfile::for($this->record);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('atp-appview-filament::identity.profile')->viewData(fn () => ['p' => $this->profile()]),
            Livewire::make(IdentityRecords::class, ['did' => $this->record->did])->key('records-'.$this->record->did),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // A dry run first, so tombstoning is never a surprise. Applying
            // reconciles again, since the repo may have changed meanwhile.
            Action::make('reconcile')
                ->label('Reconcile')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->modalHeading('Reconcile with its PDS')
                ->modalWidth('2xl')
                ->modalContent(fn () => view('atp-appview-filament::identity.reconcile-preview', $this->reconcilePreview()))
                ->modalSubmitActionLabel('Apply')
                ->modalSubmitAction(fn (Action $action) => ($this->reconcilePreview()['result']?->changed() || $this->reconcilePreview()['result']?->account !== null) ? $action : false)
                ->action(function () {
                    IdentityResource::reconcile($this->record->did);
                    $this->record->refresh();
                    $this->profile = null;
                }),
            // Everything that changes what the app does with the account.
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('block')
                        ->label('Block')
                        ->icon('heroicon-o-no-symbol')
                        ->visible(fn () => ! collect($this->profile()->blocks)->contains(fn (Block $block) => $block->isBlanket()))
                        ->modalHeading(fn () => 'Block '.$this->profile()->name())
                        ->modalDescription('Keep this repo out of the index. Records already indexed stay unless purged.')
                        ->modalWidth('lg')
                        ->schema([
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
                                $outcome = app(Blocklist::class)->block($this->record->did, $data['collection'] ?: null, $data['note'] ?: null, (bool) $data['purge']);
                            } catch (Throwable $e) {
                                Notification::make()->title('Could not block')->body(e($e->getMessage()))->danger()->send();

                                return;
                            }

                            BlockResource::report('Blocked '.$this->profile()->name(), $outcome);
                            $this->profile = null;
                        }),
                    Action::make('unblock')
                        ->label('Unblock')
                        ->icon('heroicon-o-x-mark')
                        ->visible(fn () => $this->profile()->blocks !== [])
                        ->requiresConfirmation()
                        ->modalDescription('Remove every rule naming this repo. Its records are indexed again as they arrive, and Tap is asked to backfill it. A rule on its host still applies.')
                        ->action(function () {
                            foreach ($this->profile()->blocks as $block) {
                                $outcome = app(Blocklist::class)->unblock($block->subject, $block->collection);
                            }

                            isset($outcome)
                                ? BlockResource::report('Unblocked '.$this->profile()->name(), $outcome)
                                : Notification::make()->title('Already unblocked')->send();
                            $this->profile = null;
                        }),
                ])->dropdown(false),
                ActionGroup::make([
                    Action::make('ban')
                        ->label('Ban')
                        ->icon('heroicon-o-user-minus')
                        ->color('danger')
                        ->visible(fn () => ! $this->profile()->banned())
                        ->modalHeading(fn () => 'Ban '.$this->profile()->name())
                        ->modalDescription(fn () => 'The account cannot sign in, and everything it wrote is hidden at once and pruned after '.config('atp-appview.moderation.prune_banned_after_days', 365).' days unless you cancel the pruning. Nothing new is indexed for it while banned.')
                        ->modalSubmitActionLabel('Ban')
                        ->schema([Textarea::make('reason')->rows(2)->helperText('Kept in the moderation log.')])
                        ->action(fn (array $data) => $this->act(fn ($actions) => $actions->ban($this->record->did, $data['reason'] ?: null, $this->actor()), 'Banned')),
                    Action::make('unban')
                        ->label('Unban')
                        ->icon('heroicon-o-user-plus')
                        ->visible(fn () => $this->profile()->banned())
                        ->requiresConfirmation()
                        ->modalDescription('The account can sign in again, what it wrote is shown again, and Tap is asked to send what it missed. Labels that ban it are lifted too, until they are applied again.')
                        ->action(fn () => $this->act(fn ($actions) => $actions->unban($this->record->did, $this->actor()), 'Unbanned')),
                    Action::make('review')
                        ->label('Needs review')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->visible(fn () => ! $this->profile()->reviewed())
                        ->modalHeading(fn () => $this->profile()->name().' needs review')
                        ->modalDescription('Everything the account wrote is left out of feeds, search and other discovery, and still shown on its own page and where linked. It can sign in and post as usual.')
                        ->modalSubmitActionLabel('Mark as needs review')
                        ->schema([Textarea::make('reason')->rows(2)->helperText('Kept in the moderation log.')])
                        ->action(fn (array $data) => $this->act(fn ($actions) => $actions->review($this->record->did, $data['reason'] ?: null, $this->actor()), 'Marked as needs review')),
                    Action::make('unreview')
                        ->label('Clear needs review')
                        ->icon('heroicon-o-eye')
                        ->visible(fn () => $this->profile()->reviewed())
                        ->requiresConfirmation()
                        ->modalDescription('What the account wrote returns to discovery.')
                        ->action(fn () => $this->act(fn ($actions) => $actions->unreview($this->record->did, $this->actor()), 'Review cleared')),
                    Action::make('cancelPrune')
                        ->label('Keep its records')
                        ->icon('heroicon-o-archive-box')
                        ->visible(fn () => $this->profile()->banned() && ! $this->profile()->pruneCancelled())
                        ->requiresConfirmation()
                        ->modalDescription('The records stay, hidden, for as long as the ban lasts, instead of being pruned.')
                        ->action(fn () => $this->act(fn ($actions) => $actions->cancelPrune($this->record->did), 'Pruning cancelled')),
                    Action::make('resumePrune')
                        ->label('Prune its records when due')
                        ->icon('heroicon-o-trash')
                        ->visible(fn () => $this->profile()->banned() && $this->profile()->pruneCancelled())
                        ->requiresConfirmation()
                        ->action(fn () => $this->act(fn ($actions) => $actions->resumePrune($this->record->did), 'Pruning resumed')),
                ])->dropdown(false),
                ActionGroup::make([
                    Action::make('takedown')
                        ->label('Take down on its PDS')
                        ->icon('heroicon-o-server-stack')
                        ->color('danger')
                        ->visible(fn () => $this->profile()->hosted && ! $this->profile()->takenDown())
                        ->modalHeading(fn () => 'Take down '.$this->profile()->name())
                        ->modalDescription('The account is taken down on the PDS you run: its repo stops being served to the network. This is separate from a ban, which only affects this app.')
                        ->modalSubmitActionLabel('Take down')
                        ->schema([Textarea::make('reason')->rows(2)->helperText('Kept in the moderation log.')])
                        ->action(fn (array $data) => $this->act(fn ($actions) => $actions->takedown($this->record->did, $data['reason'] ?: null, $this->actor()), 'Taken down')),
                    Action::make('restore')
                        ->label('Restore on its PDS')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn () => $this->profile()->hosted && $this->profile()->takenDown())
                        ->requiresConfirmation()
                        ->modalDescription('The PDS serves the account again.')
                        ->action(fn () => $this->act(fn ($actions) => $actions->restore($this->record->did, $this->actor()), 'Restored')),
                ])->dropdown(false),
            ])
                ->label('Moderate')
                ->icon('heroicon-m-shield-exclamation')
                ->color('danger')
                ->button(),
            Action::make('failures')
                ->label(fn () => 'Retry failures ('.number_format($this->failures()).')')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->outlined()
                ->visible(fn () => $this->failures() > 0)
                ->modalHeading('Retry failed deliveries')
                ->modalDescription(fn () => 'Events from this repo that ingest parked. Retry runs them as they were delivered, reindex fetches each record from the PDS as it is now.')
                ->modalWidth('lg')
                ->schema([
                    Radio::make('how')
                        ->options(['retry' => 'Retry as delivered', 'reindex' => 'Reindex from the PDS'])
                        ->default('retry')
                        ->required(),
                ])
                ->action(function (array $data) {
                    FailedDeliveryResource::report($data['how'], FailedDelivery::query()->where('did', $this->record->did)->orderBy('id')->get()->all());
                    $this->failures = null;
                }),
            // Lookups first, then links, out of the way of what changes things.
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('refresh')
                        ->label('Refresh identity')
                        ->icon('heroicon-o-identification')
                        ->action(fn () => $this->refreshIdentity()),
                    Action::make('resync')
                        ->label('Resync in Tap')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->modalDescription('Ask Tap to walk the repo again and send any record it never delivered. Records already indexed are kept, and live events for this repo wait until the resync finishes.')
                        ->action(fn () => $this->resync()),
                    Action::make('tap')
                        ->label('Tap status')
                        ->icon('heroicon-o-signal')
                        ->modalHeading('Tap status')
                        ->modalWidth('2xl')
                        ->modalContent(function () {
                            try {
                                return Json::render(app(TapAdmin::class)->info($this->record->did));
                            } catch (Throwable $e) {
                                return new HtmlString('<p class="atp-reconcile-error">'.e('Tap did not answer: '.$e->getMessage()).'</p>');
                            }
                        })
                        ->modalSubmitAction(false),
                    Action::make('profile')
                        ->label('Profile record')
                        ->icon('heroicon-o-code-bracket')
                        ->modalHeading(fn () => $this->profile()->name())
                        ->modalWidth('4xl')
                        ->modalContent(fn () => Json::render($this->profile()->profile, $this->record->did, $this->record->pds))
                        ->modalSubmitAction(false),
                ])->dropdown(false),
                // Elsewhere in the panel.
                ActionGroup::make([
                    Action::make('records')
                        ->label('Open in Records')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn () => IdentityResource::recordsPage())
                        ->url(fn () => RecordResource::getUrl('index', ['filters' => ['did' => ['value' => $this->record->did]]])),
                ])->dropdown(false),
                // Outside the panel, in a new tab.
                ActionGroup::make([
                    // By DID, which outlives a handle change.
                    Action::make('bluesky')
                        ->label('Open in Bluesky')
                        ->icon('heroicon-o-user-circle')
                        ->url(fn () => 'https://bsky.app/profile/'.$this->record->did, shouldOpenInNewTab: true),
                    Action::make('open')
                        ->label('Open repo')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn () => Links::repo($this->record->did), shouldOpenInNewTab: true),
                    Action::make('pds')
                        ->label('Open PDS')
                        ->icon('heroicon-o-globe-alt')
                        ->visible(fn () => $this->record->pds !== null)
                        ->url(fn () => $this->record->pds, shouldOpenInNewTab: true),
                    Action::make('plc')
                        ->label('PLC audit log')
                        ->icon('heroicon-o-finger-print')
                        ->visible(fn () => str_starts_with($this->record->did, 'did:plc:'))
                        ->url(fn () => 'https://plc.directory/'.$this->record->did.'/log/audit', shouldOpenInNewTab: true),
                ])->dropdown(false),
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }
}

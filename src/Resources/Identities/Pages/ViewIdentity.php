<?php

namespace SocialDept\AtpAppviewFilament\Resources\Identities\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use SocialDept\AtpAppview\Blocklist\Blocklist;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppviewFilament\Resources\Blocks\BlockResource;
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
            Action::make('reconcile')
                ->label('Reconcile')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    IdentityResource::reconcile($this->record->did);
                    $this->record->refresh();
                    $this->profile = null;
                }),
            Action::make('block')
                ->label('Block')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->outlined()
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
                        Notification::make()->title('Could not block')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    BlockResource::report('Blocked '.$this->profile()->name(), $outcome);
                    $this->profile = null;
                }),
            Action::make('unblock')
                ->label('Unblock')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->outlined()
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
            // Links and read-only views, out of the way of what changes things.
            ActionGroup::make([
                Action::make('profile')
                    ->label('Profile record')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading(fn () => $this->profile()->name())
                    ->modalWidth('4xl')
                    ->modalContent(fn () => Json::render($this->profile()->profile, $this->record->did, $this->record->pds))
                    ->modalSubmitAction(false),
                Action::make('records')
                    ->label('Open in Records')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn () => IdentityResource::recordsPage())
                    ->url(fn () => RecordResource::getUrl('index', ['filters' => ['did' => ['value' => $this->record->did]]])),
                Action::make('open')
                    ->label('Open repo')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => Links::repo($this->record->did), shouldOpenInNewTab: true),
                // By DID, which outlives a handle change.
                Action::make('bluesky')
                    ->label('Open in Bluesky')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => 'https://bsky.app/profile/'.$this->record->did, shouldOpenInNewTab: true),
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
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }
}

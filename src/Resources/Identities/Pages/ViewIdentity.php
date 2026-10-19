<?php

namespace SocialDept\AtpAppviewFilament\Resources\Identities\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Support\IdentityProfile;
use SocialDept\AtpAppviewFilament\Support\Json;
use SocialDept\AtpAppviewFilament\Support\Links;
use SocialDept\AtpAppviewFilament\Widgets\IdentityRecords;

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
            View::make('atp-appview-filament::identity.profile')->viewData(['p' => $this->profile()]),
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
            Action::make('profile')
                ->label('Profile record')
                ->icon('heroicon-o-code-bracket')
                ->color('gray')
                ->modalHeading(fn () => $this->profile()->name())
                ->modalWidth('4xl')
                ->modalContent(fn () => Json::render($this->profile()->profile, $this->record->did, $this->record->pds))
                ->modalSubmitAction(false),
            Action::make('open')
                ->label('Open repo')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => Links::repo($this->record->did), shouldOpenInNewTab: true),
        ];
    }
}

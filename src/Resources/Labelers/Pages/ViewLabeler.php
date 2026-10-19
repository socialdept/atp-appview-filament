<?php

namespace SocialDept\AtpAppviewFilament\Resources\Labelers\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use SocialDept\AtpAppview\Models\Labeler;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;
use SocialDept\AtpAppviewFilament\Resources\Labelers\LabelerResource;
use SocialDept\AtpAppviewFilament\Widgets\LabelerValues;

/**
 * One labeler: the values it declares and what each does here.
 *
 * @property Labeler $record
 */
class ViewLabeler extends ViewRecord
{
    protected static string $resource = LabelerResource::class;

    public function getTitle(): string|Htmlable
    {
        $profile = LabelerResource::profiles([$this->record->did])[$this->record->did] ?? [];

        return $profile['displayName'] ?? $profile['handle'] ?? $this->record->did;
    }

    /**
     * Labelers, then this one's name, without the trailing "View".
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [LabelerResource::getUrl('index') => 'Labelers', (string) $this->getTitle()];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Livewire::make(LabelerValues::class, ['did' => $this->record->did])->key('values-'.$this->record->did),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('identity')
                ->label('Open identity')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->visible(fn () => \SocialDept\AtpAppview\Models\Identity::query()->whereKey($this->record->did)->exists())
                ->url(fn () => IdentityResource::getUrl('view', ['record' => $this->record->did])),
            Action::make('profile')
                ->label('Open in Bluesky')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => 'https://bsky.app/profile/'.$this->record->did, shouldOpenInNewTab: true),
        ];
    }
}

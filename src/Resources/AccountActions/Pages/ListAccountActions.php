<?php

namespace SocialDept\AtpAppviewFilament\Resources\AccountActions\Pages;

use Filament\Resources\Pages\ListRecords;
use SocialDept\AtpAppviewFilament\Resources\AccountActions\AccountActionResource;

class ListAccountActions extends ListRecords
{
    protected static string $resource = AccountActionResource::class;

    protected ?string $heading = 'Moderation log';

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}

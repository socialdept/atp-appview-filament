<?php

namespace SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\Pages;

use Filament\Resources\Pages\ListRecords;
use SocialDept\AtpAppviewFilament\Resources\FailedDeliveries\FailedDeliveryResource;
use SocialDept\AtpAppviewFilament\Widgets\IngestTallies;

class ListFailedDeliveries extends ListRecords
{
    protected static string $resource = FailedDeliveryResource::class;

    protected function getFooterWidgets(): array
    {
        return [IngestTallies::class];
    }
}

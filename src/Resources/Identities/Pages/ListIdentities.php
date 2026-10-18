<?php

namespace SocialDept\AtpAppviewFilament\Resources\Identities\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use SocialDept\AtpAppviewFilament\Resources\Identities\IdentityResource;

class ListIdentities extends ListRecords
{
    protected static string $resource = IdentityResource::class;

    /**
     * Count records for the page shown, after pagination. As a withCount()
     * subquery, Postgres runs it for every row the offset skips too, which
     * makes deep pages slow.
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();

        $page = $records instanceof Collection ? $records : $records->getCollection();

        if ($page->isNotEmpty() && ! array_key_exists('records_count', $page->first()->getAttributes())) {
            $page->loadCount('records');
        }

        return $records;
    }
}

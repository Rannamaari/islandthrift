<?php

namespace App\Filament\Resources\WebsiteOrders\Pages;

use App\Filament\Resources\WebsiteOrders\WebsiteOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListWebsiteOrders extends ListRecords
{
    protected static string $resource = WebsiteOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

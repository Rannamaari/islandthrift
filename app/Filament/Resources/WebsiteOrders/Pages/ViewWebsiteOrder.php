<?php

namespace App\Filament\Resources\WebsiteOrders\Pages;

use App\Filament\Resources\Sales\Pages\ViewSale;
use App\Filament\Resources\WebsiteOrders\WebsiteOrderResource;

class ViewWebsiteOrder extends ViewSale
{
    protected static string $resource = WebsiteOrderResource::class;
}

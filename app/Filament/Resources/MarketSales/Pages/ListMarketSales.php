<?php

namespace App\Filament\Resources\MarketSales\Pages;

use App\Filament\Pages\Market\SalesDesk;
use App\Filament\Resources\MarketSales\MarketSaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMarketSales extends ListRecords
{
    protected static string $resource = MarketSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('desk')
                ->label('نافذة البيع')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->url(SalesDesk::getUrl()),
        ];
    }
}

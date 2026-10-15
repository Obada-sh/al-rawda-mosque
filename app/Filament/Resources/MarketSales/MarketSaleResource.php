<?php

namespace App\Filament\Resources\MarketSales;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\MarketSales\Pages\ListMarketSales;
use App\Filament\Support\Market\MarketSalesTable;
use App\Models\MarketSale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The register of every purchase at the points markets: who bought what,
 * when, and for how many points. Purchases are made at the sales desk.
 */
class MarketSaleResource extends Resource
{
    protected static ?string $model = MarketSale::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Market;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'عملية شراء';

    protected static ?string $pluralModelLabel = 'سجل المشتريات';

    protected static ?string $slug = 'market-purchases';

    public static function table(Table $table): Table
    {
        return MarketSalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketSales::route('/'),
        ];
    }
}

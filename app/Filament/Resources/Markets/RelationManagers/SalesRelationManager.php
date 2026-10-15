<?php

namespace App\Filament\Resources\Markets\RelationManagers;

use App\Filament\Support\Market\MarketSalesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * What the market sold, newest first. Sales are made at the sales desk; a
 * sale made by mistake is cancelled here.
 */
class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    protected static ?string $title = 'المبيعات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return MarketSalesTable::configure($table, showMarket: false);
    }
}

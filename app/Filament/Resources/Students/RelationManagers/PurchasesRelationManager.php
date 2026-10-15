<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Filament\Support\Market\MarketSalesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * What the student bought at the points markets, in every course. Their
 * balance in each course is on the courses tab.
 */
class PurchasesRelationManager extends RelationManager
{
    protected static string $relationship = 'marketSales';

    protected static ?string $title = 'المشتريات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return MarketSalesTable::configure($table, showStudent: false);
    }
}

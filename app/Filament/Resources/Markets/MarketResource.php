<?php

namespace App\Filament\Resources\Markets;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Markets\Pages\EditMarket;
use App\Filament\Resources\Markets\Pages\ListMarkets;
use App\Filament\Resources\Markets\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Markets\RelationManagers\SalesRelationManager;
use App\Filament\Resources\Markets\Schemas\MarketForm;
use App\Filament\Resources\Markets\Tables\MarketsTable;
use App\Models\Market;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The points markets (سوق النقاط): each belongs to a course, offers items
 * priced in points, and sells them at the sales desk to the course's
 * students. A market is opened from the list, and filled with its items on
 * its own page.
 */
class MarketResource extends Resource
{
    protected static ?string $model = Market::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Market;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'سوق';

    protected static ?string $pluralModelLabel = 'الأسواق';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MarketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MarketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            SalesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarkets::route('/'),
            'edit' => EditMarket::route('/{record}/edit'),
        ];
    }
}

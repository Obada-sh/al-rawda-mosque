<?php

namespace App\Filament\Resources\TajweedLevels;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\TajweedLevels\Pages\ManageTajweedLevels;
use App\Filament\Resources\TajweedLevels\Schemas\TajweedLevelForm;
use App\Filament\Resources\TajweedLevels\Tables\TajweedLevelsTable;
use App\Models\TajweedLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TajweedLevelResource extends Resource
{
    protected static ?string $model = TajweedLevel::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Settings;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'مستوى تجويد';

    protected static ?string $pluralModelLabel = 'مستويات التجويد';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TajweedLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TajweedLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTajweedLevels::route('/'),
        ];
    }
}

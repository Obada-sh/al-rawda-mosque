<?php

namespace App\Filament\English\Resources\EnglishExpenses;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishExpenses\Pages\ManageEnglishExpenses;
use App\Filament\English\Resources\EnglishExpenses\Schemas\EnglishExpenseForm;
use App\Filament\English\Resources\EnglishExpenses\Tables\EnglishExpensesTable;
use App\Models\English\EnglishExpense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What the English section spends, in departments of its own. Its income
 * is what the students pay (see the payments and the money report).
 */
class EnglishExpenseResource extends Resource
{
    protected static ?string $model = EnglishExpense::class;

    protected static ?string $slug = 'expenses';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصاريف';

    protected static ?string $recordTitleAttribute = 'item_name';

    public static function form(Schema $schema): Schema
    {
        return EnglishExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnglishExpensesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('department');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishExpenses::route('/'),
        ];
    }
}

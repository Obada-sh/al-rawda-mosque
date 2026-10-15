<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What the mosque spends. Only expenses are recorded: there is no income
 * and no balance.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصاريف';

    protected static ?string $recordTitleAttribute = 'item_name';

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('department');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}

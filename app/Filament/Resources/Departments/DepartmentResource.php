<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Resources\Departments\Schemas\DepartmentForm;
use App\Filament\Resources\Departments\Tables\DepartmentsTable;
use App\Models\Department;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The departments an expense can belong to.
 */
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'قسم';

    protected static ?string $pluralModelLabel = 'الأقسام';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}

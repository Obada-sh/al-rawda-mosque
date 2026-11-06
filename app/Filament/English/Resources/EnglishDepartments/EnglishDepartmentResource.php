<?php

namespace App\Filament\English\Resources\EnglishDepartments;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishDepartments\Pages\ManageEnglishDepartments;
use App\Filament\English\Resources\EnglishDepartments\Schemas\EnglishDepartmentForm;
use App\Filament\English\Resources\EnglishDepartments\Tables\EnglishDepartmentsTable;
use App\Models\English\EnglishDepartment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The departments an expense of the English section can belong to: a
 * list of its own, apart from the mosque's.
 */
class EnglishDepartmentResource extends Resource
{
    protected static ?string $model = EnglishDepartment::class;

    protected static ?string $slug = 'departments';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Finance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'قسم';

    protected static ?string $pluralModelLabel = 'الأقسام';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EnglishDepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnglishDepartmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEnglishDepartments::route('/'),
        ];
    }
}

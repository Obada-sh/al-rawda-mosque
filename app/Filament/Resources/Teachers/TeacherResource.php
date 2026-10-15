<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Filament\Resources\Teachers\Tables\TeachersTable;
use App\Models\Teacher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The one list of teachers of the mosque. Circle supervisors and
 * assistants, the teachers who hear recitations and the examiners are all
 * chosen from it.
 */
class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Students;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'أستاذ';

    protected static ?string $pluralModelLabel = 'الأساتذة';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return TeacherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeachers::route('/'),
        ];
    }
}

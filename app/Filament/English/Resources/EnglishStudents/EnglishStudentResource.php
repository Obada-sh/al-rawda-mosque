<?php

namespace App\Filament\English\Resources\EnglishStudents;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishStudents\Pages\CreateEnglishStudent;
use App\Filament\English\Resources\EnglishStudents\Pages\EditEnglishStudent;
use App\Filament\English\Resources\EnglishStudents\Pages\ListEnglishStudents;
use App\Filament\English\Resources\EnglishStudents\Pages\ViewEnglishStudent;
use App\Filament\English\Resources\EnglishStudents\RelationManagers\EnglishCoursesRelationManager;
use App\Filament\English\Resources\EnglishStudents\RelationManagers\EnglishPaymentsRelationManager;
use App\Filament\English\Resources\EnglishStudents\RelationManagers\EnglishResultsRelationManager;
use App\Filament\English\Resources\EnglishStudents\Schemas\EnglishStudentForm;
use App\Filament\English\Resources\EnglishStudents\Schemas\EnglishStudentInfolist;
use App\Filament\English\Resources\EnglishStudents\Tables\EnglishStudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The students of the English section: the same people as the mosque's
 * students table, those on the English list. A student of both sections
 * is the same record in both: what one section writes of them (a phone
 * number, a photo) the other sees.
 */
class EnglishStudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $slug = 'students';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Courses;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return EnglishStudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EnglishStudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnglishStudentsTable::configure($table);
    }

    /**
     * The English list only: a student of the mosque alone is not found
     * here, in the list, the search or by the address of the page.
     *
     * The list shows each student's level and current courses, so they are
     * loaded with the students instead of one query per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->inEnglish()->withCount('englishEnrollments')->with([
            'englishLevel',
            'englishEnrollments' => fn ($query) => $query
                ->whereNull('left_on')
                ->whereHas('course', fn ($query) => $query->active())
                ->with(['course', 'hall']),
        ]);
    }

    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'phone', 'guardian_phone'];
    }

    public static function getRelations(): array
    {
        return [
            EnglishCoursesRelationManager::class,
            EnglishPaymentsRelationManager::class,
            EnglishResultsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnglishStudents::route('/'),
            'create' => CreateEnglishStudent::route('/create'),
            'view' => ViewEnglishStudent::route('/{record}'),
            'edit' => EditEnglishStudent::route('/{record}/edit'),
        ];
    }
}

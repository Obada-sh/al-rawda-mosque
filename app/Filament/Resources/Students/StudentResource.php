<?php

namespace App\Filament\Resources\Students;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Filament\Resources\Students\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Students\RelationManagers\JuzExamsRelationManager;
use App\Filament\Resources\Students\RelationManagers\PointTransactionsRelationManager;
use App\Filament\Resources\Students\RelationManagers\PurchasesRelationManager;
use App\Filament\Resources\Students\RelationManagers\RecitationsRelationManager;
use App\Filament\Resources\Students\RelationManagers\TransfersRelationManager;
use App\Filament\Resources\Students\Schemas\StudentForm;
use App\Filament\Resources\Students\Schemas\StudentInfolist;
use App\Filament\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Students;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'طالب';

    protected static ?string $pluralModelLabel = 'الطلاب';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    /**
     * The list shows each student's level, current circles and waiting
     * exams, and its buttons need how many courses each student has, so
     * they are loaded with the students instead of one query per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('enrollments')->with([
            'tajweedLevel',
            'juzExams' => fn ($query) => $query->pending(),
            'enrollments' => fn ($query) => $query
                ->whereNull('left_on')
                ->whereHas('course', fn ($query) => $query->active())
                ->with(['course', 'circle']),
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
            EnrollmentsRelationManager::class,
            RecitationsRelationManager::class,
            PointTransactionsRelationManager::class,
            PurchasesRelationManager::class,
            ActivitiesRelationManager::class,
            JuzExamsRelationManager::class,
            TransfersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }
}

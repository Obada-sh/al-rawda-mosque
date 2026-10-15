<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\RelationManagers\CirclesRelationManager;
use App\Filament\Resources\Courses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\SessionsRelationManager;
use App\Filament\Resources\Courses\RelationManagers\TransfersRelationManager;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * A course, with everything inside it: its weekly timetable, its circles,
 * its students, its sessions and its transfers log.
 */
class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Students;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'دورة';

    protected static ?string $pluralModelLabel = 'الدورات';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('schedules');
    }

    public static function getRelations(): array
    {
        return [
            CirclesRelationManager::class,
            EnrollmentsRelationManager::class,
            SessionsRelationManager::class,
            TransfersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\English\Resources\EnglishCourses;

use App\Filament\English\EnglishPanelGroup;
use App\Filament\English\Resources\EnglishCourses\Pages\CreateEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\Pages\EditEnglishCourse;
use App\Filament\English\Resources\EnglishCourses\Pages\ListEnglishCourses;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\EnrollmentsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\HallsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\PapersRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\SessionsRelationManager;
use App\Filament\English\Resources\EnglishCourses\RelationManagers\TestsRelationManager;
use App\Filament\English\Resources\EnglishCourses\Schemas\EnglishCourseForm;
use App\Filament\English\Resources\EnglishCourses\Tables\EnglishCoursesTable;
use App\Models\English\EnglishCourse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * An English course, with everything inside it: its class days, its halls,
 * its students and what they paid, its sessions, its tests and its papers.
 */
class EnglishCourseResource extends Resource
{
    protected static ?string $model = EnglishCourse::class;

    protected static ?string $slug = 'courses';

    protected static string|UnitEnum|null $navigationGroup = EnglishPanelGroup::Courses;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'دورة';

    protected static ?string $pluralModelLabel = 'الدورات';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EnglishCourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnglishCoursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['schedules', 'level']);
    }

    public static function getRelations(): array
    {
        return [
            HallsRelationManager::class,
            EnrollmentsRelationManager::class,
            SessionsRelationManager::class,
            TestsRelationManager::class,
            PapersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnglishCourses::route('/'),
            'create' => CreateEnglishCourse::route('/create'),
            'edit' => EditEnglishCourse::route('/{record}/edit'),
        ];
    }
}

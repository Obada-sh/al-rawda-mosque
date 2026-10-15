<?php

namespace App\Filament\Resources\TeacherAttendances;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Filament\Resources\TeacherAttendances\Tables\TeacherAttendancesTable;
use App\Models\TeacherAttendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The attendance register of the teachers (supervisors and assistants):
 * every scanned teacher's card and every attendance added by hand.
 */
class TeacherAttendanceResource extends Resource
{
    protected static ?string $model = TeacherAttendance::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 25;

    protected static ?string $navigationLabel = 'حضور الأساتذة';

    protected static ?string $modelLabel = 'حضور أستاذ';

    protected static ?string $pluralModelLabel = 'حضور الأساتذة';

    protected static ?string $slug = 'teacher-attendances';

    public static function table(Table $table): Table
    {
        return TeacherAttendancesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'teacher',
            // The circles of the course tell what the teacher does in it.
            'session.course.circles',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeacherAttendances::route('/'),
        ];
    }
}

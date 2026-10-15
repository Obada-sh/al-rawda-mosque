<?php

namespace App\Filament\Resources\Attendances;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\Attendances\Pages\ManageAttendances;
use App\Filament\Resources\Attendances\Tables\AttendancesTable;
use App\Models\Attendance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The attendance register: every scan and every attendance added by hand.
 */
class AttendanceResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'سجل الحضور';

    protected static ?string $modelLabel = 'حضور';

    protected static ?string $pluralModelLabel = 'سجل الحضور';

    public static function table(Table $table): Table
    {
        return AttendancesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'session',
            'enrollment.student',
            'enrollment.course',
            'enrollment.circle',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAttendances::route('/'),
        ];
    }
}

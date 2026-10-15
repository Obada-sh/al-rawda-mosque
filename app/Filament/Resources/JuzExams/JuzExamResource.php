<?php

namespace App\Filament\Resources\JuzExams;

use App\Enums\ExamStatus;
use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\JuzExams\Pages\ManageJuzExams;
use App\Filament\Resources\JuzExams\Tables\JuzExamsTable;
use App\Models\JuzExam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Juz exams. An exam opens by itself when a student completes a juz; the
 * admin records its result here.
 */
class JuzExamResource extends Resource
{
    protected static ?string $model = JuzExam::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'السبر';

    protected static ?string $modelLabel = 'سبر';

    protected static ?string $pluralModelLabel = 'السبر';

    public static function table(Table $table): Table
    {
        return JuzExamsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student', 'examiner', 'enrollment.course']);
    }

    /**
     * The number of exams waiting to be taken, shown next to the menu item.
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = JuzExam::query()->where('status', ExamStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'سبر منتظر';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJuzExams::route('/'),
        ];
    }
}

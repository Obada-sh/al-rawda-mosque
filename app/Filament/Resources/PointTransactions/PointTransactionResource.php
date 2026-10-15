<?php

namespace App\Filament\Resources\PointTransactions;

use App\Filament\Enums\PanelGroup;
use App\Filament\Resources\PointTransactions\Pages\ManagePointTransactions;
use App\Filament\Resources\PointTransactions\Tables\PointTransactionsTable;
use App\Models\PointTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The points ledger. Attendance and recitation points appear here by
 * themselves; the admin adds rewards and penalties by hand, with a reason.
 */
class PointTransactionResource extends Resource
{
    protected static ?string $model = PointTransaction::class;

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Daily;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'النقاط';

    protected static ?string $modelLabel = 'نقاط';

    protected static ?string $pluralModelLabel = 'النقاط';

    protected static ?string $slug = 'points';

    public static function table(Table $table): Table
    {
        return PointTransactionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'enrollment.student',
            'enrollment.course',
            'enrollment.circle',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePointTransactions::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\PointTransactions\Pages;

use App\Actions\Points\AwardManualPoints;
use App\Filament\Resources\PointTransactions\PointTransactionResource;
use App\Filament\Resources\PointTransactions\Tables\PointTransactionsTable;
use App\Filament\Support\Attempt;
use App\Filament\Support\Fields\EnrollmentSelect;
use App\Models\PointTransaction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePointTransactions extends ManageRecords
{
    protected static string $resource = PointTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('award')
                ->label('إضافة نقاط')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('إضافة نقاط أو خصمها')
                ->modalSubmitActionLabel('حفظ')
                ->schema([
                    EnrollmentSelect::make()->columnSpanFull(),
                    ...PointTransactionsTable::pointsFields(),
                ])
                ->action(fn (array $data, Action $action, AwardManualPoints $awardManualPoints): PointTransaction => Attempt::action(
                    $action,
                    fn (): PointTransaction => $awardManualPoints->handle($data),
                ))
                ->successNotificationTitle('تم حفظ النقاط'),
        ];
    }
}

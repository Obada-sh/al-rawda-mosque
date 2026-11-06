<?php

namespace App\Filament\English\Resources\EnglishPayments\Pages;

use App\Actions\English\Payments\RecordEnglishPayment;
use App\Filament\English\Pages\EnglishPaymentsReport;
use App\Filament\English\Resources\EnglishPayments\EnglishPaymentResource;
use App\Filament\English\Support\EnglishEnrollmentActions;
use App\Filament\English\Support\EnglishEnrollmentSelect;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishPayment;
use App\Services\English\EnglishBalances;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ManageEnglishPayments extends ManageRecords
{
    protected static string $resource = EnglishPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('تقرير الأقساط')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(fn (): string => EnglishPaymentsReport::getUrl()),
            Action::make('pay')
                ->label('تسجيل دفعة')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('تسجيل دفعة طالب')
                ->modalSubmitActionLabel('تسجيل الدفعة')
                ->schema([
                    EnglishEnrollmentSelect::make(current: false)->live(),
                    Text::make(fn (Get $get): string => self::account($get('english_enrollment_id')))
                        ->visible(fn (Get $get): bool => filled($get('english_enrollment_id'))),
                    ...EnglishEnrollmentActions::paymentFields(),
                ])
                ->action(fn (array $data, Action $action, RecordEnglishPayment $recordPayment): EnglishPayment => Attempt::action(
                    $action,
                    fn (): EnglishPayment => $recordPayment->handle($data),
                ))
                ->successNotificationTitle('تم تسجيل الدفعة'),
        ];
    }

    private static function account(mixed $enrollmentId): string
    {
        $enrollment = filled($enrollmentId) ? EnglishEnrollment::query()->with('course')->find($enrollmentId) : null;

        return $enrollment === null ? '' : EnglishEnrollmentActions::accountLine(app(EnglishBalances::class)->of($enrollment));
    }
}

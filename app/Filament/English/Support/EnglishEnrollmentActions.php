<?php

namespace App\Filament\English\Support;

use App\Actions\English\Enrollments\ChangeEnglishHall;
use App\Actions\English\Enrollments\ChangeEnglishPrice;
use App\Actions\English\Enrollments\DeleteEnglishEnrollment;
use App\Actions\English\Enrollments\UpdateEnglishEnrollmentDates;
use App\Actions\English\Payments\RecordEnglishPayment;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishEnrollment;
use App\Services\English\EnglishBalance;
use App\Services\English\EnglishBalances;
use App\Support\Dates;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * The buttons of a row of English enrollments. They are the same whether
 * the table is opened from the course or from the student's file.
 */
final class EnglishEnrollmentActions
{
    /**
     * Moves the student to another hall of the same course.
     */
    public static function changeHall(): Action
    {
        return Action::make('hall')
            ->label('نقل')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (EnglishEnrollment $record): bool => ! $record->hasLeft())
            ->modalHeading(fn (EnglishEnrollment $record): string => "نقل {$record->student->full_name} إلى قاعة أخرى")
            ->modalDescription('حضور الطالب ودفعاته وعلاماته في الدورة تبقى له.')
            ->modalSubmitActionLabel('نقل الطالب')
            ->schema([
                Select::make('english_hall_id')
                    ->label('القاعة الجديدة')
                    ->options(fn (EnglishEnrollment $record): array => collect(EnglishOptions::halls($record->english_course_id))
                        ->except($record->english_hall_id)
                        ->all())
                    ->markAsRequired(),
            ])
            ->action(fn (EnglishEnrollment $record, array $data, Action $action, ChangeEnglishHall $changeHall) => Attempt::action(
                $action,
                fn () => $changeHall->handle($record, $data),
            ))
            ->successNotificationTitle('تم نقل الطالب');
    }

    /**
     * Records money the student paid for the course. The window says what
     * is due, what was paid and what remains; the amount offered is what
     * remains.
     */
    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('دفعة')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (EnglishEnrollment $record): bool => self::balanceOf($record)->remaining() > 0)
            ->modalHeading(fn (EnglishEnrollment $record): string => "دفعة من {$record->student->full_name} عن {$record->course->name}")
            ->modalDescription(fn (EnglishEnrollment $record): string => self::accountLine(self::balanceOf($record)))
            ->modalSubmitActionLabel('تسجيل الدفعة')
            ->fillForm(fn (EnglishEnrollment $record): array => [
                'amount' => self::balanceOf($record)->remaining(),
                'paid_on' => Dates::today()->toDateString(),
            ])
            ->schema(self::paymentFields())
            ->action(fn (EnglishEnrollment $record, array $data, Action $action, RecordEnglishPayment $recordPayment) => Attempt::action(
                $action,
                fn () => $recordPayment->handle([...$data, 'english_enrollment_id' => $record->getKey()]),
            ))
            ->successNotificationTitle('تم تسجيل الدفعة');
    }

    /**
     * The student's account: from the sum the table loaded with the row,
     * or asked for when the row came without it.
     */
    private static function balanceOf(EnglishEnrollment $record): EnglishBalance
    {
        return array_key_exists('payments_sum_amount', $record->getAttributes())
            ? EnglishMoneyColumns::balance($record)
            : app(EnglishBalances::class)->of($record);
    }

    /**
     * "السعر: 150,000 ل.س · المدفوع: 50,000 ل.س · المتبقي: 100,000 ل.س"
     */
    public static function accountLine(EnglishBalance $balance): string
    {
        return 'السعر: '.Money::pounds($balance->due)
            .' · المدفوع: '.Money::pounds($balance->paid)
            .' · المتبقي: '.Money::pounds($balance->remaining());
    }

    /**
     * The amount, the day and a note of a payment.
     *
     * @return list<mixed>
     */
    public static function paymentFields(): array
    {
        return [
            TextInput::make('amount')
                ->label('المبلغ (ل.س)')
                ->helperText('بالليرة السورية، بلا فواصل.')
                ->inputMode('numeric')
                ->extraInputAttributes(['dir' => 'ltr'])
                ->markAsRequired(),
            DatePicker::make('paid_on')
                ->label('تاريخ الدفع')
                ->default(fn (): string => Dates::today()->toDateString())
                ->maxDate(fn () => Dates::today())
                ->markAsRequired(),
            TextInput::make('notes')
                ->label('ملاحظات')
                ->placeholder('مثلاً: القسط الأول'),
        ];
    }

    /**
     * Opens the printable attendance card of the student in a new tab.
     */
    public static function printCard(): Action
    {
        return Action::make('card')
            ->label('البطاقة')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (EnglishEnrollment $record): bool => ! $record->hasLeft())
            ->url(fn (EnglishEnrollment $record): string => EnglishPresenter::cardsUrl($record->english_course_id, enrollmentIds: [$record->getKey()]))
            ->openUrlInNewTab();
    }

    /**
     * Gives the student a price of their own (a discount, with its reason),
     * or takes it away.
     */
    public static function changePrice(): Action
    {
        return Action::make('price')
            ->label('السعر')
            ->icon(Heroicon::OutlinedTag)
            ->color('gray')
            ->modalHeading(fn (EnglishEnrollment $record): string => "سعر الدورة لـ {$record->student->full_name}")
            ->modalDescription(fn (EnglishEnrollment $record): string => 'سعر الدورة: '.Money::pounds($record->course->price).'. اترك السعر الخاص فارغاً ليدفع الطالب سعر الدورة.')
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (EnglishEnrollment $record): array => [
                'price' => $record->price,
                'price_note' => $record->price_note,
            ])
            ->schema(self::priceFields())
            ->action(fn (EnglishEnrollment $record, array $data, Action $action, ChangeEnglishPrice $changePrice) => Attempt::action(
                $action,
                fn () => $changePrice->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ السعر');
    }

    /**
     * The special price and its reason, as the enrollment form asks them too.
     *
     * @return list<TextInput>
     */
    public static function priceFields(): array
    {
        return [
            TextInput::make('price')
                ->label('سعر خاص (ل.س)')
                ->helperText('اختياري: لطالب له خصم أو منحة. اكتب المبلغ كله بالليرة السورية بلا فواصل.')
                ->inputMode('numeric')
                ->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('price_note')
                ->label('سبب السعر الخاص')
                ->placeholder('مثلاً: خصم أخوة'),
        ];
    }

    /**
     * Corrects the enrollment date, and records (or clears) the day the
     * student left the course.
     */
    public static function editDates(): Action
    {
        return Action::make('dates')
            ->label('التواريخ')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->modalHeading(fn (EnglishEnrollment $record): string => "تواريخ {$record->student->full_name} في الدورة")
            ->modalSubmitActionLabel('حفظ')
            ->fillForm(fn (EnglishEnrollment $record): array => [
                'enrolled_on' => $record->enrolled_on->toDateString(),
                'left_on' => $record->left_on?->toDateString(),
            ])
            ->schema([
                DatePicker::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                DatePicker::make('left_on')
                    ->label('تاريخ الخروج من الدورة')
                    ->helperText('اتركه فارغاً ما دام الطالب في الدورة.')
                    ->maxDate(fn () => Dates::today()),
            ])
            ->action(fn (EnglishEnrollment $record, array $data, Action $action, UpdateEnglishEnrollmentDates $updateDates) => Attempt::action(
                $action,
                fn () => $updateDates->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ التواريخ');
    }

    /**
     * Removes an enrollment made by mistake (one with no records at all).
     */
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->label('حذف التسجيل')
            ->modalHeading(fn (EnglishEnrollment $record): string => "حذف تسجيل {$record->student->full_name}")
            ->modalDescription('يُحذف فقط التسجيل الذي ليس له أي سجل. إذا كان للطالب حضور أو دفعات أو علامات فسجّل تاريخ خروجه من الدورة بدلاً من الحذف.')
            ->using(fn (EnglishEnrollment $record, DeleteEnglishEnrollment $deleteEnrollment): bool => Attempt::confirmed(function () use ($record, $deleteEnrollment): bool {
                $deleteEnrollment->handle($record);

                return true;
            }));
    }
}

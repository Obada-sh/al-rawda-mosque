<?php

namespace App\Filament\English\Support;

use App\Actions\Attendance\BulkAttendanceResult;
use App\Actions\English\Attendance\RecordEnglishBulkAttendance;
use App\Filament\Support\Attempt;
use App\Filament\Support\Attendance\BulkAttendanceAction;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * The window that records the attendance of many students of an English
 * course on one day: ticked by name (a hall at a time, or all of them), or
 * typed as card numbers. It is on the attendance list and on the desk.
 */
final class EnglishBulkAttendanceAction
{
    public const NAME = 'bulkAttendance';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('حضور جماعي')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->modalHeading('تسجيل حضور جماعي')
            ->modalDescription('سجّل حضور عدد من طلاب دورة في يوم واحد: حدّدهم بأسمائهم، أو اكتب أرقام بطاقاتهم، أو الاثنين معاً.')
            ->modalSubmitActionLabel('تسجيل الحضور')
            ->modalWidth('4xl')
            ->schema([
                Grid::make(['md' => 3])
                    ->schema([
                        Select::make('english_course_id')
                            ->label('الدورة')
                            ->options(fn (): array => EnglishOptions::activeCourses())
                            ->default(fn (): ?int => EnglishOptions::soleActiveCourseId())
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('english_hall_id', null);
                                $set('english_enrollment_ids', []);
                            })
                            ->markAsRequired(),
                        Select::make('english_hall_id')
                            ->label('القاعة')
                            ->placeholder(fn (Get $get): string => blank($get('english_course_id')) ? 'اختر الدورة أولاً' : 'كل القاعات')
                            ->options(fn (Get $get): array => EnglishOptions::hallsOfCourse($get('english_course_id')))
                            ->helperText('لعرض طلاب قاعة واحدة فقط.')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('english_enrollment_ids', []))
                            ->dehydrated(false),
                        DatePicker::make('attended_on')
                            ->label('تاريخ الحضور')
                            ->default(fn (): string => Dates::today()->toDateString())
                            ->maxDate(fn () => Dates::today())
                            ->markAsRequired(),
                    ]),
                CheckboxList::make('english_enrollment_ids')
                    ->label('الطلاب')
                    ->options(fn (Get $get): array => EnglishOptions::studentsOfCourse($get('english_course_id'), $get('english_hall_id')))
                    ->searchable()
                    ->searchPrompt('ابحث باسم الطالب')
                    ->noSearchResultsMessage('لا يوجد طالب بهذا الاسم.')
                    ->bulkToggleable()
                    ->columns(['md' => 2, 'xl' => 3])
                    ->helperText(fn (Get $get): string => blank($get('english_course_id')) ? 'اختر الدورة لتظهر أسماء طلابها.' : 'حدّد من حضر، أو «تحديد الكل» ثم أزل من غاب.'),
                Textarea::make('card_codes')
                    ->label('أو أرقام البطاقات')
                    ->rows(3)
                    ->placeholder('رقم في كل سطر، أو بينها مسافات. يمكن مسح البطاقات بالماسح هنا واحدة بعد الأخرى.')
                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric']),
            ])
            ->action(function (array $data, Action $action, RecordEnglishBulkAttendance $recordBulk): void {
                BulkAttendanceAction::tell(Attempt::action($action, fn (): BulkAttendanceResult => $recordBulk->handle($data)));
            });
    }
}

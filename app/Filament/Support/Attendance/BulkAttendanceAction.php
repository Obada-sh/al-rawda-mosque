<?php

namespace App\Filament\Support\Attendance;

use App\Actions\Attendance\BulkAttendanceResult;
use App\Actions\Attendance\RecordBulkAttendance;
use App\Filament\Support\Attempt;
use App\Filament\Support\Options;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * The window that records the attendance of many students of a course
 * on one day: ticked by name (a circle at a time, or all of them), or
 * typed as card numbers (the scanner can read the cards one after the
 * other into the box). It is on the attendance list and on the scan desk.
 */
final class BulkAttendanceAction
{
    public const NAME = 'bulkAttendance';

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label('حضور جماعي')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('gray')
            ->modalHeading('تسجيل حضور جماعي')
            ->modalDescription('سجّل حضور عدد من طلاب دورة في يوم واحد: حدّدهم بأسمائهم، أو اكتب أرقام بطاقاتهم، أو الاثنين معاً. يكسب كل طالب نقاط الحضور كما في المسح.')
            ->modalSubmitActionLabel('تسجيل الحضور')
            ->modalWidth('4xl')
            ->schema([
                Grid::make(['md' => 3])
                    ->schema([
                        Select::make('course_id')
                            ->label('الدورة')
                            ->options(fn (): array => Options::activeCourses())
                            ->default(fn (): ?int => Options::soleActiveCourseId())
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('circle_id', null);
                                $set('enrollment_ids', []);
                            })
                            ->markAsRequired(),
                        Select::make('circle_id')
                            ->label('الحلقة')
                            ->placeholder(fn (Get $get): string => blank($get('course_id')) ? 'اختر الدورة أولاً' : 'كل الحلقات')
                            ->options(fn (Get $get): array => Options::circlesOfCourse($get('course_id')))
                            ->helperText('لعرض طلاب حلقة واحدة فقط.')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('enrollment_ids', []))
                            ->dehydrated(false),
                        DatePicker::make('attended_on')
                            ->label('تاريخ الحضور')
                            ->default(fn (): string => Dates::today()->toDateString())
                            ->maxDate(fn () => Dates::today())
                            ->markAsRequired(),
                    ]),
                CheckboxList::make('enrollment_ids')
                    ->label('الطلاب')
                    ->options(fn (Get $get): array => Options::studentsOfCourse($get('course_id'), $get('circle_id')))
                    ->searchable()
                    ->searchPrompt('ابحث باسم الطالب')
                    ->noSearchResultsMessage('لا يوجد طالب بهذا الاسم.')
                    ->bulkToggleable()
                    ->columns(['md' => 2, 'xl' => 3])
                    ->helperText(fn (Get $get): string => blank($get('course_id')) ? 'اختر الدورة لتظهر أسماء طلابها.' : 'حدّد من حضر، أو «تحديد الكل» ثم أزل من غاب.'),
                Textarea::make('card_codes')
                    ->label('أو أرقام البطاقات')
                    ->rows(3)
                    ->placeholder('رقم في كل سطر، أو بينها مسافات. يمكن مسح البطاقات بالماسح هنا واحدة بعد الأخرى.')
                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric']),
            ])
            ->action(function (array $data, Action $action, RecordBulkAttendance $recordBulkAttendance): void {
                $result = Attempt::action($action, fn (): BulkAttendanceResult => $recordBulkAttendance->handle($data));

                self::tell($result);
            });
    }

    private static function tell(BulkAttendanceResult $result): void
    {
        $notification = Notification::make()
            ->title($result->title())
            ->body(implode('<br>', array_map(fn (string $line): string => e($line), $result->details())));

        if ($result->hasProblems()) {
            // What was not recorded stays on the screen until it is read.
            $notification->warning()->persistent();
        } elseif ($result->recorded === []) {
            $notification->info();
        } else {
            $notification->success();
        }

        $notification->send();
    }
}

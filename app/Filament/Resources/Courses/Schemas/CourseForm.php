<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Enums\CourseStatus;
use App\Filament\Support\Options;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الدورة')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم الدورة')
                            ->markAsRequired()
                            ->autofocus(),
                        Select::make('status')
                            ->label('حالة الدورة')
                            ->helperText('الدورة المنتهية لا يُسجَّل فيها طلاب جدد ولا يُقبل فيها مسح البطاقات.')
                            ->options(CourseStatus::options())
                            ->selectablePlaceholder(false)
                            ->visibleOn('edit'),
                        DatePicker::make('start_date')
                            ->label('تاريخ البداية')
                            ->markAsRequired(),
                        DatePicker::make('end_date')
                            ->label('تاريخ النهاية')
                            ->helperText('اتركه فارغاً إذا لم يُحدَّد بعد.'),
                        TextInput::make('points_per_page')
                            ->label('نقاط كل صفحة جديدة')
                            ->helperText('تُمنح مرة واحدة عن كل صفحة يسمّعها الطالب لأول مرة.')
                            ->inputMode('numeric')
                            ->default('0')
                            ->markAsRequired(),
                        TextInput::make('points_per_attendance')
                            ->label('نقاط كل يوم حضور')
                            ->inputMode('numeric')
                            ->default('0')
                            ->markAsRequired(),
                    ]),
                Section::make('أيام الدوام')
                    ->description('الأيام التي يُقبل فيها مسح بطاقات الحضور. يمكن دائماً إضافة حضور يدوي في أي يوم آخر.')
                    ->columnSpanFull()
                    ->schema([
                        // The labels are not shown (the section and the table head say it all),
                        // but a screen reader reads them.
                        Repeater::make('schedules')
                            ->label('أيام الدوام')
                            ->hiddenLabel()
                            ->table([
                                // Wide enough for the longest day name on a narrow screen.
                                TableColumn::make('اليوم')->markAsRequired()->width('40%'),
                                TableColumn::make('من الساعة')->markAsRequired(),
                                TableColumn::make('إلى الساعة')->markAsRequired(),
                            ])
                            ->schema([
                                Select::make('weekday')
                                    ->label('اليوم')
                                    ->options(Options::weekdays())
                                    ->selectablePlaceholder(false)
                                    ->wrapOptionLabels(false)
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                TimePicker::make('start_time')->label('من الساعة'),
                                TimePicker::make('end_time')->label('إلى الساعة'),
                            ])
                            ->addActionLabel('إضافة يوم')
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->maxItems(7),
                    ]),
            ]);
    }
}

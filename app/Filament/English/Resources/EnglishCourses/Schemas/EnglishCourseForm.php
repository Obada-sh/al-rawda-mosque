<?php

namespace App\Filament\English\Resources\EnglishCourses\Schemas;

use App\Enums\CourseStatus;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\Support\Options;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnglishCourseForm
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
                        Select::make('english_level_id')
                            ->label('المستوى')
                            ->helperText('بطاقات طلاب الدورة تُطبع بتصميم هذا المستوى.')
                            ->options(fn (): array => EnglishOptions::levels())
                            ->markAsRequired(),
                        TextInput::make('price')
                            ->label('سعر الدورة (ل.س)')
                            ->helperText('ما يدفعه الطالب عن الدورة كلها بالليرة السورية، بلا فواصل. يمكن إعطاء طالب سعراً خاصاً عند تسجيله.')
                            ->inputMode('numeric')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->markAsRequired(),
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
                    ]),
                Section::make('أيام الدوام')
                    ->description('الأيام التي يُقبل فيها مسح بطاقات الحضور. يمكن دائماً إضافة حضور يدوي في أي يوم آخر.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('schedules')
                            ->label('أيام الدوام')
                            ->hiddenLabel()
                            ->table([
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

<?php

namespace App\Filament\Support\Reports;

use App\Filament\Support\Options;
use App\Support\Dates;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The fields at the top of every report page: course, circle and period.
 * Their names are the ones the report filter reads.
 */
final class ReportFilterFields
{
    public static function course(): Select
    {
        return Select::make('course_id')
            ->label('الدورة')
            ->placeholder('كل الدورات')
            ->options(fn (): array => Options::courses())
            ->searchable()
            // The circles offered belong to the chosen course.
            ->afterStateUpdated(fn (Set $set) => $set('circle_id', null));
    }

    public static function circle(): Select
    {
        return Select::make('circle_id')
            ->label('الحلقة')
            ->placeholder(fn (Get $get): string => blank($get('course_id')) ? 'اختر الدورة أولاً' : 'كل الحلقات')
            ->options(fn (Get $get): array => Options::circlesOfCourse($get('course_id')))
            ->disabled(fn (Get $get): bool => blank($get('course_id')));
    }

    public static function from(): DatePicker
    {
        return DatePicker::make('from')
            ->label('من تاريخ')
            ->maxDate(fn (Get $get) => $get('until') ?: Dates::today());
    }

    public static function until(): DatePicker
    {
        return DatePicker::make('until')
            ->label('إلى تاريخ')
            ->minDate(fn (Get $get) => $get('from'))
            ->maxDate(fn () => Dates::today());
    }
}

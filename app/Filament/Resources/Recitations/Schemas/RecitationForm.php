<?php

namespace App\Filament\Resources\Recitations\Schemas;

use App\Filament\Support\Fields\EnrollmentSelect;
use App\Filament\Support\Options;
use App\Models\Recitation;
use App\Support\Dates;
use App\Support\QuranPages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RecitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                EnrollmentSelect::make()
                    ->visibleOn('create')
                    ->columnSpanFull(),
                // The student of a recitation cannot be changed: one entered
                // for the wrong student is deleted and entered again.
                TextEntry::make('student')
                    ->label('الطالب')
                    ->state(fn (?Recitation $record): ?string => EnrollmentSelect::label($record?->enrollment))
                    ->visibleOn('edit')
                    ->columnSpanFull(),
                TextInput::make('from_page')
                    ->label('من صفحة')
                    ->inputMode('numeric')
                    ->placeholder((string) QuranPages::FIRST)
                    ->markAsRequired(),
                TextInput::make('to_page')
                    ->label('إلى صفحة')
                    ->helperText('لصفحة واحدة اكتب الرقم نفسه في الحقلين.')
                    ->inputMode('numeric')
                    ->placeholder((string) QuranPages::LAST)
                    ->markAsRequired(),
                Select::make('teacher_id')
                    ->label('الأستاذ المسمِّع')
                    ->options(fn (?Recitation $record): array => Options::teachers($record?->teacher_id))
                    ->searchable()
                    ->markAsRequired(),
                DatePicker::make('recited_on')
                    ->label('تاريخ التسميع')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}

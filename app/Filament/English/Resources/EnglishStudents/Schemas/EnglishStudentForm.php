<?php

namespace App\Filament\English\Resources\EnglishStudents\Schemas;

use App\Filament\English\Support\EnglishOptions;
use App\Filament\Support\Fields\PhotoUpload;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use App\Support\Dates;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The student's form in the English section: the mosque's, with the
 * English level in place of the tajweed level and of the pages memorized.
 * Only these fields are sent, so the mosque's are never touched from here.
 */
class EnglishStudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الطالب')
                    ->description('الاسم فقط مطلوب. بقية الحقول يمكن إكمالها لاحقاً، وسيبقى الملف معلَّماً بأنه غير مكتمل حتى تُملأ.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        ...self::details(),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('الصورة الشخصية')
                    ->description('اختيارية. تظهر في ملف الطالب وعند مسح بطاقته، وعلى بطاقته.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_photo')
                            ->label('الصورة الحالية')
                            ->state(fn (?Student $record): ?string => StudentPresenter::photoUrl($record))
                            ->circular()
                            ->imageSize(96)
                            ->visible(fn (?Student $record): bool => filled($record?->photo_path)),
                        // Cut to the circle of the card in the browser, then checked and kept by the SaveStudent action.
                        PhotoUpload::make()
                            ->label(fn (?Student $record): string => filled($record?->photo_path) ? 'صورة جديدة بدل الحالية' : 'الصورة'),
                        Toggle::make('remove_photo')
                            ->label('إزالة الصورة الحالية')
                            ->visible(fn (?Student $record): bool => filled($record?->photo_path)),
                    ]),
            ]);
    }

    /**
     * The student's details, also asked when a new student is registered
     * straight into a course.
     *
     * @return list<Component>
     */
    public static function details(): array
    {
        return [
            TextInput::make('full_name')
                ->label('اسم الطالب')
                ->markAsRequired()
                ->autofocus(),
            TextInput::make('father_name')
                ->label('اسم الأب')
                ->helperText('اختياري. يميّز الطالب عن غيره ممن يحمل الاسم نفسه.'),
            TextInput::make('father_work')
                ->label('عمل الأب')
                ->helperText('اختياري.'),
            DatePicker::make('birth_date')
                ->label('تاريخ الميلاد')
                ->maxDate(fn () => Dates::today()->subDay()),
            TextInput::make('phone')
                ->label('رقم الطالب')
                ->inputMode('tel')
                ->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('guardian_phone')
                ->label('رقم ولي الأمر')
                ->inputMode('tel')
                ->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make('address')
                ->label('مكان السكن'),
            Select::make('english_level_id')
                ->label('مستوى اللغة الانجليزية')
                ->options(fn (): array => EnglishOptions::levels()),
        ];
    }
}

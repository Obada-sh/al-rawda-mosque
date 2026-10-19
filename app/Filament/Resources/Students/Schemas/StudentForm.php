<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Filament\Support\Fields\PhotoUpload;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use App\Support\Dates;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentForm
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
                        TextInput::make('full_name')
                            ->label('اسم الطالب')
                            ->markAsRequired()
                            ->autofocus(),
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
                        Select::make('tajweed_level_id')
                            ->label('مستوى التجويد')
                            ->options(fn (): array => Options::tajweedLevels()),
                        TextInput::make('prior_memorized_pages')
                            ->label('المحفوظ السابق (عدد الصفحات)')
                            ->helperText('ما حفظه الطالب قبل دخوله النظام. اكتب 0 إن لم يكن قد حفظ شيئاً.')
                            ->inputMode('numeric'),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('الصورة الشخصية')
                    ->description('اختيارية. تظهر في ملف الطالب وفي قائمة الطلاب وعند مسح بطاقته، ويمكن طباعتها على البطاقة.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_photo')
                            ->label('الصورة الحالية')
                            ->state(fn (?Student $record): ?string => StudentPresenter::photoUrl($record))
                            ->circular()
                            ->imageSize(96)
                            ->visible(fn (?Student $record): bool => self::hasPhoto($record)),
                        // Cut to the circle of the card in the browser, then checked and kept by the SaveStudent action.
                        PhotoUpload::make()
                            ->label(fn (?Student $record): string => self::hasPhoto($record) ? 'صورة جديدة بدل الحالية' : 'الصورة'),
                        Toggle::make('remove_photo')
                            ->label('إزالة الصورة الحالية')
                            ->visible(fn (?Student $record): bool => self::hasPhoto($record)),
                    ]),
            ]);
    }

    private static function hasPhoto(?Student $student): bool
    {
        return filled($student?->photo_path);
    }
}

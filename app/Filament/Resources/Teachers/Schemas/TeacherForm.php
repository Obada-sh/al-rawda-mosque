<?php

namespace App\Filament\Resources\Teachers\Schemas;

use App\Filament\Support\Presenters\TeacherPresenter;
use App\Models\Teacher;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الأستاذ')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('full_name')
                            ->label('اسم الأستاذ')
                            ->markAsRequired()
                            ->autofocus(),
                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->inputMode('tel')
                            ->extraInputAttributes(['dir' => 'ltr']),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->helperText('الأستاذ غير النشط لا يظهر في قوائم الاختيار ولا تُقبل بطاقته عند المسح، وتبقى سجلاته السابقة كما هي.')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
                Section::make('الصورة الشخصية')
                    ->description('اختيارية. تُطبع على بطاقة الأستاذ وتظهر عند مسحها.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('current_photo')
                            ->label('الصورة الحالية')
                            ->state(fn (?Teacher $record): ?string => TeacherPresenter::photoUrl($record))
                            ->circular()
                            ->imageSize(96)
                            ->visible(fn (?Teacher $record): bool => self::hasPhoto($record)),
                        // The picture is cut square and made small in the browser,
                        // then checked and kept by the SaveTeacher action.
                        FileUpload::make('photo')
                            ->label(fn (?Teacher $record): string => self::hasPhoto($record) ? 'صورة جديدة بدل الحالية' : 'الصورة')
                            ->avatar()
                            ->storeFiles(false)
                            ->helperText('JPG أو PNG أو WebP. تُقصّ الصورة مربّعة تلقائياً.'),
                        Toggle::make('remove_photo')
                            ->label('إزالة الصورة الحالية')
                            ->visible(fn (?Teacher $record): bool => self::hasPhoto($record)),
                    ]),
            ]);
    }

    private static function hasPhoto(?Teacher $teacher): bool
    {
        return filled($teacher?->photo_path);
    }
}

<?php

namespace App\Filament\English\Resources\EnglishStudents\Schemas;

use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EnglishStudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الطالب')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('photo')
                            ->label('الصورة')
                            ->state(fn (Student $record): ?string => StudentPresenter::photoUrl($record))
                            ->circular()
                            ->imageSize(112)
                            ->visible(fn (Student $record): bool => filled($record->photo_path))
                            ->columnSpanFull(),
                        TextEntry::make('full_name')
                            ->label('الاسم')
                            ->weight('bold'),
                        TextEntry::make('father_name')
                            ->label('اسم الأب')
                            ->placeholder('—'),
                        TextEntry::make('father_work')
                            ->label('عمل الأب')
                            ->placeholder('—'),
                        TextEntry::make('age')
                            ->label('العمر')
                            ->suffix(' سنة')
                            ->placeholder('—'),
                        TextEntry::make('birth_date')
                            ->label('تاريخ الميلاد')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('englishLevel.name')
                            ->label('مستوى اللغة الانجليزية')
                            ->placeholder('—'),
                        TextEntry::make('phone')
                            ->label('رقم الطالب')
                            ->placeholder('—'),
                        TextEntry::make('guardian_phone')
                            ->label('رقم ولي الأمر')
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->label('مكان السكن')
                            ->placeholder('—'),
                        IconEntry::make('english_profile_complete')
                            ->label('الملف مكتمل')
                            ->boolean()
                            ->helperText(fn (Student $record): ?string => EnglishPresenter::missingFields($record)),
                        TextEntry::make('in_mosque')
                            ->label('في دورات المسجد')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نعم، طالب في المسجد أيضاً' : 'لا'),
                        TextEntry::make('notes')
                            ->label('ملاحظات')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

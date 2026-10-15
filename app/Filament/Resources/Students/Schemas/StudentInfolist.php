<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Student;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
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
                        TextEntry::make('age')
                            ->label('العمر')
                            ->suffix(' سنة')
                            ->placeholder('—'),
                        TextEntry::make('birth_date')
                            ->label('تاريخ الميلاد')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('tajweedLevel.name')
                            ->label('مستوى التجويد')
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
                        IconEntry::make('is_profile_complete')
                            ->label('الملف مكتمل')
                            ->boolean()
                            ->helperText(fn (Student $record): ?string => StudentPresenter::missingFields($record)),
                        TextEntry::make('notes')
                            ->label('ملاحظات')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('الحفظ')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('prior_memorized_pages')
                            ->label('المحفوظ السابق')
                            ->suffix(' صفحة')
                            ->placeholder('غير محدد'),
                        TextEntry::make('recited_pages_count')
                            ->label('سمّع في النظام')
                            ->helperText('صفحات مختلفة في كل الدورات، دون تكرار.')
                            ->suffix(' صفحة'),
                        TextEntry::make('memorized_pages_total')
                            ->label('مجموع المحفوظ')
                            ->suffix(' صفحة')
                            ->weight('bold'),
                        TextEntry::make('pending_exams')
                            ->label('سبر منتظر')
                            ->state(fn (Student $record): array => StudentPresenter::pendingExams($record))
                            ->badge()
                            ->color('warning')
                            ->placeholder('لا يوجد'),
                    ]),
            ]);
    }
}

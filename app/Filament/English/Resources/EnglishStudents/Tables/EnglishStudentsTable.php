<?php

namespace App\Filament\English\Resources\EnglishStudents\Tables;

use App\Actions\Students\DeleteStudent;
use App\Enums\Section;
use App\Filament\English\Support\EnglishOptions;
use App\Filament\English\Support\EnglishPresenter;
use App\Filament\Support\Attempt;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\English\EnglishEnrollment;
use App\Models\Student;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnglishStudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Photos are optional: the column is there once a student has one.
                ImageColumn::make('photo')
                    ->label('الصورة')
                    ->state(fn (Student $record): ?string => StudentPresenter::photoUrl($record))
                    ->defaultImageUrl(fn (): string => asset('img/student.svg'))
                    ->circular()
                    ->imageSize(36)
                    ->visible(fn (): bool => Student::query()->inEnglish()->whereNotNull('photo_path')->exists())
                    ->toggleable(),
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->description(fn (Student $record): ?string => StudentPresenter::father($record))
                    ->searchable(['full_name', 'father_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('englishLevel.name')
                    ->label('المستوى')
                    ->placeholder('—'),
                TextColumn::make('current_courses')
                    ->label('الدورة الحالية')
                    ->state(fn (Student $record): array => $record->englishEnrollments
                        ->map(fn (EnglishEnrollment $enrollment): string => "{$enrollment->course->name} — {$enrollment->hall->name}")
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('رقم الطالب')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('guardian_phone')
                    ->label('رقم ولي الأمر')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('age')
                    ->label('العمر')
                    ->placeholder('—')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('english_profile_complete')
                    ->label('الملف مكتمل')
                    ->boolean()
                    ->tooltip(fn (Student $record): ?string => EnglishPresenter::missingFields($record))
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('english_level_id')
                    ->label('المستوى')
                    ->options(fn (): array => EnglishOptions::levels()),
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => EnglishOptions::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->whereHas(
                            'englishEnrollments',
                            fn (Builder $query) => $query->where('english_course_id', $courseId),
                        ),
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label('الملف'),
                EditAction::make(),
                ActionGroup::make([
                    DeleteAction::make()
                        ->modalDescription('يُحذف فقط الطالب الذي لم يُسجَّل في أي دورة انجليزي (مثل طالب أُدخل بالخطأ مرتين). الطالب الذي في دورات المسجد أيضاً يُزال من قائمة الانجليزي فقط ويبقى في المسجد.')
                        ->using(fn (Student $record, DeleteStudent $deleteStudent): bool => Attempt::confirmed(function () use ($record, $deleteStudent): bool {
                            $deleteStudent->handle($record, Section::English);

                            return true;
                        })),
                ]),
            ])
            ->defaultSort('full_name');
    }
}

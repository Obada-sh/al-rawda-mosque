<?php

namespace App\Filament\Resources\Students\Tables;

use App\Actions\Students\ArchiveStudent;
use App\Actions\Students\DeleteStudent;
use App\Actions\Students\RestoreStudent;
use App\Filament\Support\Attempt;
use App\Filament\Support\Cards\CardPrintActions;
use App\Filament\Support\Documents\DocumentActions;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\StudentPresenter;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentsTable
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
                    ->visible(fn (): bool => Student::query()->whereNotNull('photo_path')->exists())
                    ->toggleable(),
                // The father's name, when there is one, under the name: it tells apart students who carry the same name.
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->description(fn (Student $record): ?string => StudentPresenter::father($record))
                    ->searchable(['full_name', 'father_name'])
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('pending_exams')
                    ->label('سبر منتظر')
                    ->state(fn (Student $record): array => StudentPresenter::pendingExams($record))
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                TextColumn::make('age')
                    ->label('العمر')
                    ->placeholder('—')
                    ->alignCenter()
                    // An older student has an earlier birth date.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('birth_date', $direction === 'asc' ? 'desc' : 'asc'))
                    ->toggleable(),
                TextColumn::make('current_circles')
                    ->label('الحلقة الحالية')
                    ->state(fn (Student $record): array => $record->enrollments
                        ->map(fn (Enrollment $enrollment): string => "{$enrollment->circle->name} ({$enrollment->course->name})")
                        ->all())
                    ->listWithLineBreaks()
                    // The longest column: it gives way first when the screen is narrow.
                    ->wrap()
                    ->placeholder('غير مسجَّل في دورة نشطة'),
                TextColumn::make('tajweedLevel.name')
                    ->label('مستوى التجويد')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('memorized_pages_total')
                    ->label('المحفوظ (صفحة)')
                    ->alignCenter(),
                // The columns below that start hidden are one click away (the columns
                // button), and a search still looks in them: with all of them shown the
                // list is wider than the screen of the laptop.
                TextColumn::make('recited_pages_count')
                    ->label('سمّع في النظام')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('رقم الطالب')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('guardian_phone')
                    ->label('رقم ولي الأمر')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('السكن')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_profile_complete')
                    ->label('الملف مكتمل')
                    ->boolean()
                    ->tooltip(fn (Student $record): ?string => StudentPresenter::missingFields($record))
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('tajweed_level_id')
                    ->label('مستوى التجويد')
                    ->options(fn (): array => Options::tajweedLevels()),
                SelectFilter::make('course')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, mixed $courseId) => $query->whereHas(
                            'enrollments',
                            fn (Builder $query) => $query->where('course_id', $courseId),
                        ),
                    )),
            ])
            ->recordActions([
                ViewAction::make()->label('الملف'),
                EditAction::make(),
                ActionGroup::make([
                    CardPrintActions::forStudent(),
                    DocumentActions::studentFile(),
                    Action::make('archive')
                        ->label('أرشفة')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->color('gray')
                        ->visible(fn (Student $record): bool => ! $record->isArchived())
                        ->requiresConfirmation()
                        ->modalHeading(fn (Student $record): string => "أرشفة {$record->full_name}")
                        ->modalDescription('الطالب المؤرشف يختفي من العمل اليومي ولا يُسجَّل له حضور أو تسميع، وتبقى كل سجلاته محفوظة. يمكن إعادة تفعيله في أي وقت.')
                        ->action(fn (Student $record, ArchiveStudent $archiveStudent) => $archiveStudent->handle($record))
                        ->successNotificationTitle('تمت أرشفة الطالب'),
                    Action::make('restore')
                        ->label('إعادة تفعيل')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->color('gray')
                        ->visible(fn (Student $record): bool => $record->isArchived())
                        ->action(fn (Student $record, RestoreStudent $restoreStudent) => $restoreStudent->handle($record))
                        ->successNotificationTitle('تمت إعادة تفعيل الطالب'),
                    DeleteAction::make()
                        ->modalDescription('يُحذف فقط الطالب الذي ليس له أي سجل (مثل طالب أُدخل بالخطأ مرتين). من له سجلات يؤرشف.')
                        ->using(fn (Student $record, DeleteStudent $deleteStudent): bool => Attempt::confirmed(function () use ($record, $deleteStudent): bool {
                            $deleteStudent->handle($record);

                            return true;
                        })),
                ]),
            ])
            ->toolbarActions([
                CardPrintActions::forSelected(),
                DocumentActions::chosenStudentFiles(),
            ])
            ->defaultSort('full_name');
    }
}

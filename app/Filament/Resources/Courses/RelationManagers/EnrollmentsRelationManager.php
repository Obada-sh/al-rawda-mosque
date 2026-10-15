<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Actions\Enrollments\EnrollStudent;
use App\Filament\Support\Attempt;
use App\Filament\Support\Enrollments\EnrollmentActions;
use App\Filament\Support\Fields\StudentSelect;
use App\Filament\Support\Options;
use App\Models\Course;
use App\Models\Enrollment;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Js;

/**
 * The students of the course: who is in which circle, and what each one
 * has done in this course so far.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'الطلاب';

    protected static ?string $modelLabel = 'تسجيل';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['student', 'circle', 'course']))
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('الطالب')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('circle.name')
                    ->label('الحلقة')
                    ->sortable(),
                TextColumn::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->date()
                    ->sortable(),
                TextColumn::make('left_on')
                    ->label('تاريخ الخروج')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('attendances_count')
                    ->label('أيام الحضور')
                    ->counts('attendances')
                    ->alignCenter(),
                TextColumn::make('recitations_sum_new_pages_count')
                    ->label('صفحات جديدة')
                    ->sum('recitations', 'new_pages_count')
                    ->placeholder('0')
                    ->alignCenter(),
                TextColumn::make('point_transactions_sum_points')
                    ->label('النقاط')
                    ->sum('pointTransactions', 'points')
                    ->placeholder('0')
                    ->alignCenter()
                    ->weight('bold'),
                TextColumn::make('card_code')
                    ->label('رقم البطاقة')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('circle_id')
                    ->label('الحلقة')
                    ->options(fn (): array => $this->course()->circles()->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('left_on')
                    ->label('الوجود في الدورة')
                    ->placeholder('الكل')
                    ->trueLabel('خرجوا من الدورة')
                    ->falseLabel('ما زالوا في الدورة')
                    ->nullable(),
            ])
            ->headerActions([
                $this->enrollAction(),
                Action::make('cards')
                    ->label('طباعة بطاقات الدورة')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (): string => route('filament.admin.attendance-cards.print', ['course_id' => $this->course()->getKey()]))
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                EnrollmentActions::transfer(),
                EnrollmentActions::printCard(),
                ActionGroup::make([
                    EnrollmentActions::editDates(),
                    EnrollmentActions::delete(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('cards')
                    ->label('طباعة بطاقات المحدَّدين')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(function (Collection $records): void {
                        $url = route('filament.admin.attendance-cards.print', [
                            'course_id' => $this->course()->getKey(),
                            'enrollment_ids' => $records->modelKeys(),
                        ]);

                        $this->js('window.open('.Js::from($url).", '_blank')");
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('enrolled_on', 'desc')
            ->emptyStateHeading('لا يوجد طلاب في الدورة بعد');
    }

    /**
     * Enrolls a student in this course. The admin picks the circle:
     * students are never placed in a circle automatically.
     */
    private function enrollAction(): Action
    {
        return Action::make('enroll')
            ->label('تسجيل طالب')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->course()->isActive())
            ->modalHeading(fn (): string => "تسجيل طالب في {$this->course()->name}")
            ->modalSubmitActionLabel('تسجيل')
            ->schema([
                StudentSelect::make(scope: fn (Builder $query) => $query->whereDoesntHave(
                    'enrollments',
                    fn (Builder $query) => $query->where('course_id', $this->course()->getKey()),
                )),
                Select::make('circle_id')
                    ->label('الحلقة')
                    ->options(fn (): array => Options::circles($this->course()->getKey()))
                    ->markAsRequired(),
                DatePicker::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
            ])
            ->action(fn (array $data, Action $action, EnrollStudent $enrollStudent): Enrollment => Attempt::action(
                $action,
                fn (): Enrollment => $enrollStudent->handle([...$data, 'course_id' => $this->course()->getKey()]),
            ))
            ->successNotificationTitle('تم تسجيل الطالب');
    }

    private function course(): Course
    {
        /** @var Course */
        return $this->getOwnerRecord();
    }
}

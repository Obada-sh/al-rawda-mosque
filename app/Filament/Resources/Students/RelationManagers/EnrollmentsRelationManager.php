<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Actions\Enrollments\EnrollStudent;
use App\Enums\CourseStatus;
use App\Filament\Support\Attempt;
use App\Filament\Support\Enrollments\EnrollmentActions;
use App\Filament\Support\Options;
use App\Filament\Support\Presenters\ReportPresenter;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Statistics\StudentStanding;
use App\Services\Statistics\StudentStandings;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The student's courses, with what they did in each one: the circle they
 * are in, their attendance, the new pages they recited, the points they
 * earned and what is left of them to spend at the course's markets.
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $title = 'الدورات';

    /** @var Collection<int, StudentStanding>|null */
    private ?Collection $standings = null;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['student', 'course', 'circle'])
                ->withSum('marketSales', 'total_points'))
            ->columns([
                TextColumn::make('course.name')
                    ->label('الدورة')
                    ->weight('medium'),
                TextColumn::make('circle.name')
                    ->label('الحلقة الحالية'),
                TextColumn::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->date(),
                TextColumn::make('left_on')
                    ->label('تاريخ الخروج')
                    ->date()
                    ->placeholder(fn (Enrollment $record): string => $record->course->status === CourseStatus::Finished
                        ? 'بقي حتى نهايتها'
                        : 'ما زال في الدورة'),
                TextColumn::make('attendances_count')
                    ->label('أيام الحضور')
                    ->counts('attendances')
                    ->alignCenter(),
                TextColumn::make('attendance_rate')
                    ->label('نسبة الحضور')
                    ->tooltip('من الجلسات التي انعقدت وهو في الدورة')
                    ->state(fn (Enrollment $record): ?float => $this->standings()->get($record->getKey())?->attendanceRate())
                    ->formatStateUsing(fn (?float $state): ?string => ReportPresenter::percent($state))
                    ->badge()
                    ->color(fn (?float $state): string => ReportPresenter::attendanceColor($state))
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('recitations_sum_new_pages_count')
                    ->label('صفحات جديدة')
                    ->sum('recitations', 'new_pages_count')
                    ->placeholder('0')
                    ->alignCenter(),
                TextColumn::make('point_transactions_sum_points')
                    ->label('النقاط')
                    ->tooltip('ما كسبه في الدورة، وهو ما يُحسب في الترتيب والإحصائيات')
                    ->sum('pointTransactions', 'points')
                    ->placeholder('0')
                    ->alignCenter()
                    ->weight('bold'),
                TextColumn::make('market_balance')
                    ->label('رصيد السوق')
                    ->tooltip('النقاط ناقص ما اشتراه من أسواق الدورة')
                    ->state(fn (Enrollment $record): int => (int) ($record->getAttributes()['point_transactions_sum_points'] ?? 0) - (int) ($record->getAttributes()['market_sales_sum_total_points'] ?? 0))
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'primary')
                    ->alignCenter(),
                TextColumn::make('card_code')
                    ->label('رقم البطاقة')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                $this->enrollAction(),
            ])
            ->recordActions([
                EnrollmentActions::transfer(),
                EnrollmentActions::printCard(),
                ActionGroup::make([
                    EnrollmentActions::editDates(),
                    EnrollmentActions::delete(),
                ]),
            ])
            ->defaultSort('enrolled_on', 'desc')
            ->emptyStateHeading('الطالب غير مسجَّل في أي دورة')
            ->paginated(false);
    }

    /**
     * Enrolls the student in a course. The admin picks the circle: students
     * are never placed in a circle automatically.
     */
    private function enrollAction(): Action
    {
        return Action::make('enroll')
            ->label('تسجيل في دورة')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => ! $this->student()->isArchived())
            ->modalHeading(fn (): string => "تسجيل {$this->student()->full_name} في دورة")
            ->modalSubmitActionLabel('تسجيل')
            ->schema([
                Select::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::activeCourses())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('circle_id', null))
                    ->markAsRequired(),
                Select::make('circle_id')
                    ->label('الحلقة')
                    ->options(fn (Get $get): array => Options::circles($get('course_id')))
                    ->placeholder('اختر الدورة أولاً ثم الحلقة')
                    ->markAsRequired(),
                DatePicker::make('enrolled_on')
                    ->label('تاريخ التسجيل')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->maxDate(fn () => Dates::today())
                    ->markAsRequired(),
            ])
            ->action(fn (array $data, Action $action, EnrollStudent $enrollStudent): Enrollment => Attempt::action(
                $action,
                fn (): Enrollment => $enrollStudent->handle([...$data, 'student_id' => $this->student()->getKey()]),
            ))
            ->successNotificationTitle('تم تسجيل الطالب في الدورة');
    }

    /**
     * What the student did in each course, computed the same way as the
     * reports (and once per page load, not once per row).
     *
     * @return Collection<int, StudentStanding> keyed by enrollment id
     */
    private function standings(): Collection
    {
        return $this->standings ??= app(StudentStandings::class)->forStudent($this->student());
    }

    private function student(): Student
    {
        /** @var Student */
        return $this->getOwnerRecord();
    }
}

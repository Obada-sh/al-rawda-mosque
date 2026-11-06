<?php

namespace App\Filament\English\Widgets\Money;

use App\Filament\English\Widgets\Money\Concerns\ReadsMoneyFilters;
use App\Services\English\EnglishCourseMoney;
use App\Services\English\EnglishMoneyReport;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;

/**
 * The payments added up course by course.
 */
class CoursesMoneyTable extends TableWidget
{
    use ReadsMoneyFilters;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('لكل دورة')
            ->records(fn (): Collection => $this->rows())
            ->columns([
                TextColumn::make('course')->label('الدورة')->weight('medium'),
                TextColumn::make('students')->label('الطلاب')->alignCenter(),
                TextColumn::make('due')->label('المطلوب')->alignEnd(),
                TextColumn::make('paid')->label('المدفوع')->alignEnd(),
                TextColumn::make('remaining')->label('المتبقي')->alignEnd()->weight('bold'),
                TextColumn::make('paid_in_full')->label('دفعوا كاملاً')->alignCenter(),
                TextColumn::make('partly')->label('دفعوا جزءاً')->alignCenter(),
                TextColumn::make('unpaid')->label('لم يدفعوا')->alignCenter(),
            ])
            ->paginated(false)
            ->emptyStateHeading('لا يوجد طلاب');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        $filter = $this->balanceFilter();

        if ($filter === null) {
            return collect();
        }

        $report = app(EnglishMoneyReport::class);
        $students = $report->students($filter);
        $courses = $report->byCourse($students);

        if ($courses->count() > 1) {
            $courses->push($report->total($students));
        }

        return $courses->mapWithKeys(fn (EnglishCourseMoney $course): array => [
            ($course->courseId ?? 'total') => [
                'course' => $course->courseName,
                'students' => $course->students,
                'due' => Money::pounds($course->due),
                'paid' => Money::pounds($course->paid),
                'remaining' => Money::pounds($course->remaining),
                'paid_in_full' => $course->paidInFull,
                'partly' => $course->partly,
                'unpaid' => $course->unpaid,
            ],
        ]);
    }
}

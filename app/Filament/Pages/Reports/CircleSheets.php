<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Enums\PanelGroup;
use App\Filament\Support\Documents\DocumentActions;
use App\Filament\Support\Options;
use App\Models\Circle;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The sheets of the circles, printed on A4 under the mosque's logo: for a
 * circle of a course, the list of its students, with the columns the
 * admin ticks (phones, the pages memorized and which, the juz exams, the
 * attendance) for the course or for every course of each student; or
 * their attendance codes (name and QR). One circle from its row, or every
 * circle of a course from the top of the page; and, from the top too, one
 * list of every student of the mosque (or of one course) by name.
 */
class CircleSheets extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'circle-sheets';

    protected static ?string $title = 'كشوف الحلقات';

    protected static string|UnitEnum|null $navigationGroup = PanelGroup::Reports;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.circle-sheets';

    protected function getHeaderActions(): array
    {
        return [
            DocumentActions::mosqueStudents(),
            DocumentActions::courseStudents(),
            DocumentActions::courseCodes(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Circle::query()
                ->with(['course', 'supervisor', 'assistant'])
                ->withCount(['enrollments' => fn (Builder $query) => $query->whereNull('left_on')]))
            ->columns([
                TextColumn::make('course.name')
                    ->label('الدورة'),
                TextColumn::make('name')
                    ->label('الحلقة')
                    ->weight('medium')
                    ->searchable(),
                TextColumn::make('supervisor.full_name')
                    ->label('المشرف')
                    ->placeholder('—'),
                TextColumn::make('assistant.full_name')
                    ->label('المساعد')
                    ->placeholder('—'),
                TextColumn::make('enrollments_count')
                    ->label('الطلاب')
                    ->alignCenter(),
            ])
            ->filters([
                // The page opens on the running course when there is only one.
                SelectFilter::make('course_id')
                    ->label('الدورة')
                    ->options(fn (): array => Options::courses())
                    ->default(Options::soleActiveCourseId()),
            ])
            ->recordActions([
                DocumentActions::circleStudents(),
                DocumentActions::circleCodes(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc(fn ($query) => $query->select('start_date')->from('courses')->whereColumn('courses.id', 'circles.course_id'))
                ->orderBy('name'))
            ->emptyStateHeading('لا توجد حلقات')
            ->emptyStateDescription('تُضاف الحلقات من صفحة الدورة.');
    }
}

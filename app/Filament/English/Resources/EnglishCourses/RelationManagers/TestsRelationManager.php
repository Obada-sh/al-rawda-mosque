<?php

namespace App\Filament\English\Resources\EnglishCourses\RelationManagers;

use App\Actions\English\Tests\DeleteEnglishTest;
use App\Actions\English\Tests\SaveEnglishTest;
use App\Actions\English\Tests\SaveEnglishTestResults;
use App\Enums\English\TestKind;
use App\Filament\Support\Attempt;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishTest;
use App\Models\English\EnglishTestResult;
use App\Support\Dates;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The exams and recitations of the course. They are held on paper or
 * aloud; here the admin writes what the test was, when, its full mark,
 * and each student's mark (or that they were absent).
 */
class TestsRelationManager extends RelationManager
{
    protected static string $relationship = 'tests';

    protected static ?string $title = 'الامتحانات والتسميع';

    protected static ?string $modelLabel = 'امتحان';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('العنوان')
                    ->placeholder('مثلاً: امتحان الوحدة الأولى')
                    ->markAsRequired()
                    ->autofocus()
                    ->columnSpanFull(),
                ToggleButtons::make('kind')
                    ->label('النوع')
                    ->options(TestKind::options())
                    ->default(TestKind::Exam->value)
                    ->inline()
                    ->markAsRequired(),
                DatePicker::make('held_on')
                    ->label('التاريخ')
                    ->default(fn (): string => Dates::today()->toDateString())
                    ->markAsRequired(),
                TextInput::make('max_mark')
                    ->label('العلامة الكاملة')
                    ->inputMode('numeric')
                    ->default('100')
                    ->markAsRequired(),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['results', 'results as absent_count' => fn (Builder $query) => $query->where('is_absent', true)])
                ->withAvg('results', 'mark'))
            ->columns([
                TextColumn::make('held_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('kind')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (TestKind $state): string => $state->label())
                    ->color(fn (TestKind $state): string => $state === TestKind::Exam ? 'primary' : 'gray'),
                TextColumn::make('max_mark')
                    ->label('العلامة الكاملة')
                    ->alignCenter(),
                TextColumn::make('results_count')
                    ->label('لهم نتيجة')
                    ->description(fn (EnglishTest $record): ?string => $record->getAttribute('absent_count') > 0 ? 'منهم غائبون: '.$record->getAttribute('absent_count') : null)
                    ->alignCenter(),
                TextColumn::make('results_avg_mark')
                    ->label('المعدل')
                    ->formatStateUsing(fn (mixed $state, EnglishTest $record): string => self::markOutOf($state, $record->max_mark))
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('امتحان أو تسميع')
                    ->using(fn (array $data, Action $action, SaveEnglishTest $saveTest): EnglishTest => Attempt::action(
                        $action,
                        fn (): EnglishTest => $saveTest->handle($this->course(), $data),
                    )),
            ])
            ->recordActions([
                $this->resultsAction(),
                EditAction::make()
                    ->using(fn (EnglishTest $record, array $data, Action $action, SaveEnglishTest $saveTest): EnglishTest => Attempt::action(
                        $action,
                        fn (): EnglishTest => $saveTest->handle($this->course(), $data, $record),
                    )),
                DeleteAction::make()
                    ->modalDescription('تُحذف مع الامتحان علامات كل الطلاب فيه.')
                    ->using(function (EnglishTest $record, DeleteEnglishTest $deleteTest): bool {
                        $deleteTest->handle($record);

                        return true;
                    }),
            ])
            ->defaultSort('held_on', 'desc')
            ->emptyStateHeading('لا توجد امتحانات بعد')
            ->emptyStateDescription('أضف امتحاناً أو تسميعاً، ثم اكتب علامات الطلاب فيه.');
    }

    /**
     * The marks of every student who was in the course on the day of the
     * test, hall by hall: a mark, or absent, and a note.
     */
    private function resultsAction(): Action
    {
        return Action::make('results')
            ->label('العلامات')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->modalHeading(fn (EnglishTest $record): string => "علامات {$record->title}")
            ->modalDescription(fn (EnglishTest $record): string => 'من '.$record->max_mark.'. اترك العلامة فارغة لمن لم يُختبر، أو حدّد «غائب».')
            ->modalSubmitActionLabel('حفظ العلامات')
            ->modalWidth('4xl')
            ->fillForm(fn (EnglishTest $record): array => [
                'marks' => $record->results
                    ->mapWithKeys(fn (EnglishTestResult $result): array => [$result->english_enrollment_id => [
                        'mark' => $result->mark === null ? null : self::plain($result->mark),
                        'is_absent' => $result->is_absent,
                        'notes' => $result->notes,
                    ]])
                    ->all(),
            ])
            ->schema(fn (EnglishTest $record, SaveEnglishTestResults $results): array => self::markFields($results->candidates($record)))
            ->action(fn (EnglishTest $record, array $data, Action $action, SaveEnglishTestResults $results): int => Attempt::action(
                $action,
                fn (): int => $results->handle($record, $data),
            ))
            ->successNotificationTitle('تم حفظ العلامات');
    }

    /**
     * One line a student, under the name of their hall.
     *
     * @param  iterable<EnglishEnrollment>  $students
     * @return list<mixed>
     */
    private static function markFields(iterable $students): array
    {
        $halls = [];

        foreach ($students as $enrollment) {
            $id = $enrollment->getKey();

            $halls[$enrollment->hall->name][] = Grid::make(['default' => 1, 'md' => 12])
                ->schema([
                    Text::make($enrollment->student->full_name)
                        ->weight('medium')
                        ->columnSpan(['md' => 4]),
                    TextInput::make("marks.{$id}.mark")
                        ->hiddenLabel()
                        ->placeholder('العلامة')
                        ->inputMode('decimal')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->columnSpan(['md' => 2]),
                    Toggle::make("marks.{$id}.is_absent")
                        ->label('غائب')
                        ->inline()
                        ->columnSpan(['md' => 2]),
                    TextInput::make("marks.{$id}.notes")
                        ->hiddenLabel()
                        ->placeholder('ملاحظة')
                        ->columnSpan(['md' => 4]),
                ]);
        }

        if ($halls === []) {
            return [Text::make('لا يوجد طلاب في الدورة يوم الامتحان.')];
        }

        return array_map(
            fn (string $hall, array $lines): Section => Section::make($hall)->schema($lines)->compact(),
            array_keys($halls),
            array_values($halls),
        );
    }

    /** "45.5 / 50" */
    private static function markOutOf(mixed $mark, int $max): string
    {
        return is_numeric($mark) ? self::plain(round((float) $mark, 2)).' / '.$max : '—';
    }

    /** A mark without the zeros after the point: "45.50" is 45.5, "40.00" is 40. */
    public static function plain(string|int|float $mark): string
    {
        $text = number_format((float) $mark, 2, '.', '');

        return rtrim(rtrim($text, '0'), '.');
    }

    private function course(): EnglishCourse
    {
        /** @var EnglishCourse */
        return $this->getOwnerRecord();
    }
}

<?php

namespace App\Filament\Support\Fields;

use App\Enums\Section;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Picks a student by typing part of the name. There can be hundreds of
 * students, so the list is searched instead of loaded whole.
 */
final class StudentSelect
{
    private const LIMIT = 50;

    /**
     * The mosque's students who are not archived.
     *
     * @param  (callable(Builder): mixed)|null  $scope  narrows which students are offered
     */
    public static function make(string $name = 'student_id', ?callable $scope = null): Select
    {
        return self::select(
            $name,
            fn (Builder $query) => $query->inMosque()->active()->when($scope, fn (Builder $query) => $scope($query)),
            null,
        );
    }

    /**
     * For enrolling in a course of the section: its own students, and those
     * of the other section too (they are added to this section's list when
     * enrolled), each of these marked "من طلاب ...". Archived students are
     * the mosque's: the mosque does not offer them, the English section does.
     *
     * @param  (callable(Builder): mixed)|null  $scope  narrows which students are offered
     */
    public static function toEnroll(Section $section, string $name = 'student_id', ?callable $scope = null): Select
    {
        return self::select(
            $name,
            fn (Builder $query) => $query
                ->when($section === Section::Mosque, fn (Builder $query) => $query->active())
                ->when($scope, fn (Builder $query) => $scope($query)),
            $section,
        );
    }

    /**
     * @param  callable(Builder): mixed  $scope
     */
    private static function select(string $name, callable $scope, ?Section $section): Select
    {
        return Select::make($name)
            ->label('الطالب')
            ->searchable()
            ->options(fn (): array => self::search(null, $scope, $section))
            ->getSearchResultsUsing(fn (string $search): array => self::search($search, $scope, $section))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::label(Student::query()->find($value), $section))
            ->searchPrompt('اكتب جزءاً من اسم الطالب')
            ->markAsRequired();
    }

    /**
     * @param  callable(Builder): mixed  $scope
     * @return array<int, string> id => name
     */
    private static function search(?string $search, callable $scope, ?Section $section): array
    {
        return Student::query()
            ->tap(fn (Builder $query) => $scope($query))
            ->when(filled($search), fn (Builder $query) => $query->where('full_name', 'like', '%'.Like::escape((string) $search).'%'))
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [$student->getKey() => self::label($student, $section)])
            ->all();
    }

    /**
     * The name, and for a student who is not yet on the section's list, the
     * section the student comes from.
     */
    private static function label(?Student $student, ?Section $section): ?string
    {
        if ($student === null) {
            return null;
        }

        if ($section === null || $student->isIn($section)) {
            return $student->full_name;
        }

        return $student->isIn($section->other())
            ? "{$student->full_name} — من طلاب {$section->other()->label()}"
            : $student->full_name;
    }
}

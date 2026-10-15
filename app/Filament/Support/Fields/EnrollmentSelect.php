<?php

namespace App\Filament\Support\Fields;

use App\Models\Enrollment;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Picks "a student in a course": the owner of every attendance, recitation
 * and point. The admin types part of the student's name; each choice shows
 * the circle and the course, so a student enrolled in two courses cannot
 * be confused.
 *
 * Only students who are still in an active course are offered. The rules
 * themselves (dates, archived students...) are enforced by the Actions.
 */
final class EnrollmentSelect
{
    private const LIMIT = 50;

    public static function make(string $name = 'enrollment_id'): Select
    {
        return Select::make($name)
            ->label('الطالب')
            ->searchable()
            ->options(fn (): array => self::search(null))
            ->getSearchResultsUsing(fn (string $search): array => self::search($search))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::label(
                Enrollment::query()->with(['student', 'course', 'circle'])->find($value),
            ))
            ->searchPrompt('اكتب جزءاً من اسم الطالب')
            ->markAsRequired();
    }

    public static function label(?Enrollment $enrollment): ?string
    {
        if ($enrollment === null) {
            return null;
        }

        return "{$enrollment->student->full_name} — {$enrollment->circle->name} ({$enrollment->course->name})";
    }

    /**
     * @return array<int, string> enrollment id => label
     */
    private static function search(?string $search): array
    {
        return Enrollment::query()
            ->with(['student', 'course', 'circle'])
            ->whereNull('left_on')
            ->whereHas('course', fn (Builder $query) => $query->active())
            ->whereHas('student', fn (Builder $query) => $query
                ->active()
                ->when(filled($search), fn (Builder $query) => $query->where('full_name', 'like', '%'.Like::escape((string) $search).'%')))
            ->orderBy(Student::query()->select('full_name')->whereColumn('students.id', 'enrollments.student_id'))
            ->limit(self::LIMIT)
            ->get()
            ->mapWithKeys(fn (Enrollment $enrollment): array => [$enrollment->getKey() => self::label($enrollment)])
            ->all();
    }
}

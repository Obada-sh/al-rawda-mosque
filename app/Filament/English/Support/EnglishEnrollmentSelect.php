<?php

namespace App\Filament\English\Support;

use App\Filament\Support\Fields\Like;
use App\Models\English\EnglishEnrollment;
use App\Models\Student;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Picks "a student in an English course": the owner of every attendance,
 * payment and result. The admin types part of the student's name; each
 * choice shows the hall and the course, so a student of two courses
 * cannot be confused.
 */
final class EnglishEnrollmentSelect
{
    private const LIMIT = 50;

    /**
     * @param  bool  $current  only students still in a running course (attendance); otherwise every enrollment (a payment may come after the course)
     */
    public static function make(string $name = 'english_enrollment_id', bool $current = true): Select
    {
        return Select::make($name)
            ->label('الطالب')
            ->searchable()
            ->options(fn (): array => self::search(null, $current))
            ->getSearchResultsUsing(fn (string $search): array => self::search($search, $current))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::label(
                EnglishEnrollment::query()->with(['student', 'course', 'hall'])->find($value),
            ))
            ->searchPrompt('اكتب جزءاً من اسم الطالب')
            ->markAsRequired();
    }

    public static function label(?EnglishEnrollment $enrollment): ?string
    {
        if ($enrollment === null) {
            return null;
        }

        return "{$enrollment->student->full_name} — {$enrollment->hall->name} ({$enrollment->course->name})";
    }

    /**
     * @return array<int, string> enrollment id => label
     */
    private static function search(?string $search, bool $current): array
    {
        return EnglishEnrollment::query()
            ->with(['student', 'course', 'hall'])
            ->when($current, fn (Builder $query) => $query
                ->whereNull('left_on')
                ->whereHas('course', fn (Builder $query) => $query->active()))
            ->when(filled($search), fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $query) => $query->where('full_name', 'like', '%'.Like::escape((string) $search).'%'),
            ))
            ->orderBy(Student::query()->select('full_name')->whereColumn('students.id', 'english_enrollments.student_id'))
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get()
            ->mapWithKeys(fn (EnglishEnrollment $enrollment): array => [$enrollment->getKey() => self::label($enrollment)])
            ->all();
    }
}

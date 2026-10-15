<?php

namespace App\Filament\Support\Filters;

use App\Filament\Support\Options;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows a table of attendance, recitations, points or exams to one
 * course. Those records belong to an enrollment, and the enrollment knows
 * its course.
 */
final class CourseFilter
{
    public static function make(string $enrollmentRelationship = 'enrollment'): SelectFilter
    {
        return SelectFilter::make('course')
            ->label('الدورة')
            ->options(fn (): array => Options::courses())
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, mixed $courseId) => $query->whereHas(
                    $enrollmentRelationship,
                    fn (Builder $query) => $query->where('course_id', $courseId),
                ),
            ));
    }
}

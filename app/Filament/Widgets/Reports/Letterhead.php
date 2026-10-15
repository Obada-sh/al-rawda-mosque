<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Department;
use App\Support\Brand;
use App\Support\Dates;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The heading of a report, on the screen and on paper: the mosque's logo
 * and name, the name of the report and what it covers (the choices of the
 * filter: course, circle, department, period), and the day it was made.
 *
 * It reads the filter of the page it stands on, so it changes with it.
 */
class Letterhead extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    /** Listed by the report pages, not found on its own. */
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.letterhead';

    /** The name of the report (the title of its page). */
    public string $heading = '';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'name' => app(Brand::class)->name(),
            'scope' => $this->scope(),
            'today' => Dates::show(Dates::today()),
        ];
    }

    /**
     * What the report covers, in words: "دورة أيلول 2026 · حلقة النور · من 01/10/2026 إلى 12/10/2026".
     *
     * @return list<string>
     */
    private function scope(): array
    {
        $filters = $this->pageFilters ?? [];
        $parts = [];

        if (array_key_exists('course_id', $filters)) {
            $parts[] = $this->nameOf(Course::class, $filters['course_id']) ?? 'كل الدورات';
        }

        if (($circle = $this->nameOf(Circle::class, $filters['circle_id'] ?? null)) !== null) {
            $parts[] = $circle;
        }

        if (array_key_exists('department_id', $filters)) {
            $parts[] = $this->nameOf(Department::class, $filters['department_id']) ?? 'كل الأقسام';
        }

        $parts[] = $this->period($filters['from'] ?? null, $filters['until'] ?? null);

        return $parts;
    }

    /**
     * @param  class-string<Course|Circle|Department>  $model
     */
    private function nameOf(string $model, mixed $id): ?string
    {
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }

        return $model::query()->whereKey((int) $id)->value('name');
    }

    private function period(mixed $from, mixed $until): string
    {
        $from = $this->day($from);
        $until = $this->day($until);

        return match (true) {
            $from !== null && $until !== null => "من {$from} إلى {$until}",
            $from !== null => "من {$from} حتى اليوم",
            $until !== null => "من البداية حتى {$until}",
            default => 'من البداية حتى اليوم',
        };
    }

    /**
     * A day of the filter as the admin reads it, or null for no day or one that is not a
     * date. The date fields hold "Y-m-d" or "Y-m-d H:i:s".
     */
    private function day(mixed $value): ?string
    {
        if (! is_string($value)
            || preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: \d{2}:\d{2}(?::\d{2})?)?$/', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return Dates::show(substr($value, 0, 10));
    }
}

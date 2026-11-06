<?php

namespace App\Filament\English\Widgets;

use App\Enums\English\PaymentState;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishDepartment;
use App\Models\English\EnglishHall;
use App\Support\Brand;
use App\Support\Dates;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * The heading of a report of the English section, on the screen and on
 * paper: the mosque's letterhead, with the name of the report and what it
 * covers (course, hall, where the students stand, department, period).
 */
class EnglishLetterhead extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    /** Listed by the report pages, not found on its own. */
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.letterhead';

    /** The name of the report (the title of its page). */
    public string $heading = '';

    /** Whether the report covers a period (the money report) or not (the payments of the students). */
    public bool $withPeriod = false;

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
     * @return list<string>
     */
    private function scope(): array
    {
        $filters = $this->pageFilters ?? [];
        $parts = ['دورة انجليزي'];

        $parts[] = $this->nameOf(EnglishCourse::class, $filters['english_course_id'] ?? null) ?? 'كل الدورات';

        if (($hall = $this->nameOf(EnglishHall::class, $filters['english_hall_id'] ?? null)) !== null) {
            $parts[] = $hall;
        }

        if (($state = PaymentState::tryFrom((string) ($filters['state'] ?? ''))) !== null) {
            $parts[] = $state->label();
        }

        if (($department = $this->nameOf(EnglishDepartment::class, $filters['english_department_id'] ?? null)) !== null) {
            $parts[] = $department;
        }

        if ($this->withPeriod) {
            $parts[] = $this->period($filters['from'] ?? null, $filters['until'] ?? null);
        }

        return $parts;
    }

    /**
     * @param  class-string<EnglishCourse|EnglishHall|EnglishDepartment>  $model
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

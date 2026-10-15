<?php

namespace App\Services\Statistics;

use App\Http\Requests\Reports\ReportFilterRequest;
use App\Support\Dates;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Validation\ValidationException;

/**
 * The scope of a report: a course, one of its circles, and a period.
 *
 * Every report reads the same filter, so the same choice always means the
 * same students and the same days on every screen and in every export.
 */
final readonly class ReportFilter
{
    /**
     * @param  string|null  $from  first day of the period (Y-m-d), or null for "since the beginning"
     * @param  string|null  $until  last day of the period (Y-m-d), or null for "until today"
     */
    public function __construct(
        public ?int $courseId = null,
        public ?int $circleId = null,
        public ?string $from = null,
        public ?string $until = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $data = ReportFilterRequest::validateData($input);

        return new self(
            courseId: isset($data['course_id']) ? (int) $data['course_id'] : null,
            circleId: isset($data['circle_id']) ? (int) $data['circle_id'] : null,
            from: isset($data['from']) ? Dates::day($data['from']) : null,
            until: isset($data['until']) ? Dates::day($data['until']) : null,
        );
    }

    /**
     * The filter, or null when the input is not a valid one. Reports that
     * are redrawn while the admin is still choosing use this, and show
     * nothing until the choice is valid.
     *
     * @param  array<string, mixed>  $input
     */
    public static function tryFromInput(array $input): ?self
    {
        try {
            return self::fromInput($input);
        } catch (ValidationException) {
            return null;
        }
    }

    public function hasPeriod(): bool
    {
        return $this->from !== null || $this->until !== null;
    }

    /**
     * Keeps only the rows whose date falls inside the period.
     */
    public function applyPeriod(Builder $query, string $dateColumn): void
    {
        if ($this->from !== null) {
            $query->where($dateColumn, '>=', $this->from);
        }

        if ($this->until !== null) {
            $query->where($dateColumn, '<=', $this->until);
        }
    }

    /**
     * Keeps only the enrollments of the chosen course and circle that were
     * running at some point of the period. A student belongs to the circle
     * they are in now, with everything they did before moving to it.
     */
    public function applyEnrollments(Builder $query, string $table = 'enrollments'): void
    {
        if ($this->courseId !== null) {
            $query->where("{$table}.course_id", $this->courseId);
        }

        if ($this->circleId !== null) {
            $query->where("{$table}.circle_id", $this->circleId);
        }

        if ($this->until !== null) {
            $query->where("{$table}.enrolled_on", '<=', $this->until);
        }

        if ($this->from !== null) {
            $query->where(fn (Builder $query) => $query
                ->whereNull("{$table}.left_on")
                ->orWhere("{$table}.left_on", '>=', $this->from));
        }
    }
}

<?php

namespace App\Services\English;

use App\Http\Requests\English\EnglishFinanceFilterRequest;
use App\Support\Dates;
use Illuminate\Validation\ValidationException;

/**
 * The scope of the English section's money report: a period, and
 * optionally one course (its payments) and one department (its expenses).
 */
final readonly class EnglishFinanceFilter
{
    /**
     * @param  string|null  $from  first day (Y-m-d), or null for "since the beginning"
     * @param  string|null  $until  last day (Y-m-d), or null for "until today"
     */
    public function __construct(
        public ?string $from = null,
        public ?string $until = null,
        public ?int $courseId = null,
        public ?int $departmentId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $data = EnglishFinanceFilterRequest::validateData($input);

        return new self(
            from: isset($data['from']) ? Dates::day($data['from']) : null,
            until: isset($data['until']) ? Dates::day($data['until']) : null,
            courseId: isset($data['english_course_id']) ? (int) $data['english_course_id'] : null,
            departmentId: isset($data['english_department_id']) ? (int) $data['english_department_id'] : null,
        );
    }

    /**
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
}

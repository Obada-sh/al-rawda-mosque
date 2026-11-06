<?php

namespace App\Services\English;

use App\Enums\English\PaymentState;
use App\Http\Requests\English\EnglishBalanceFilterRequest;
use Illuminate\Validation\ValidationException;

/**
 * The scope of the report of the students' payments: one course or all,
 * one hall, where the students stand, and whether those who left their
 * course are counted (they may still owe).
 */
final readonly class EnglishBalanceFilter
{
    public function __construct(
        public ?int $courseId = null,
        public ?int $hallId = null,
        public ?PaymentState $state = null,
        public bool $withLeft = true,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function fromInput(array $input): self
    {
        $data = EnglishBalanceFilterRequest::validateData($input);

        return new self(
            courseId: isset($data['english_course_id']) ? (int) $data['english_course_id'] : null,
            hallId: isset($data['english_hall_id']) ? (int) $data['english_hall_id'] : null,
            state: isset($data['state']) ? PaymentState::from($data['state']) : null,
            withLeft: (bool) ($data['with_left'] ?? true),
        );
    }

    /**
     * The filter, or null when the input is not a valid one.
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
}

<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when valid input would break a business rule.
 *
 * Input shape is checked by the Request classes (ValidationException);
 * this exception is for rules that need the current state of the system,
 * for example "the student is already enrolled in this course".
 *
 * $rule is a stable machine key (used by tests and logs); the message is
 * the Arabic text shown to the admin.
 */
final class BusinessRuleViolation extends DomainException
{
    private function __construct(
        public readonly string $rule,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function because(string $rule, string $message): self
    {
        return new self($rule, $message);
    }
}

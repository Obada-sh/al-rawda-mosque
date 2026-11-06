<?php

namespace Tests\Concerns;

use App\Actions\English\Enrollments\EnrollEnglishStudent;
use App\Models\English\EnglishCourse;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishHall;
use App\Models\English\EnglishPayment;
use App\Models\Student;

/**
 * Small builders for the English section, so each test states only what
 * matters to it. Everything goes through the real actions.
 */
trait BuildsEnglish
{
    /**
     * Enrolls a student in an English course through the EnrollEnglishStudent
     * action. Missing pieces are created (an English-only student, a course
     * of 150,000 SYP, a hall); the enrollment date defaults to the course start.
     */
    protected function enrollInEnglish(
        ?Student $student = null,
        ?EnglishCourse $course = null,
        ?EnglishHall $hall = null,
        ?string $enrolledOn = null,
        ?int $price = null,
        ?string $priceNote = null,
    ): EnglishEnrollment {
        $student ??= Student::factory()->english()->create();
        $course ??= $hall?->course ?? EnglishCourse::factory()->create();
        $hall ??= EnglishHall::factory()->create(['english_course_id' => $course->getKey()]);

        return app(EnrollEnglishStudent::class)->handle([
            'student_id' => $student->getKey(),
            'english_course_id' => $course->getKey(),
            'english_hall_id' => $hall->getKey(),
            'enrolled_on' => $enrolledOn ?? $course->start_date->toDateString(),
            'price' => $price,
            'price_note' => $priceNote ?? ($price === null ? null : 'خصم'),
        ]);
    }

    /**
     * A payment already in the books, written straight to the table.
     */
    protected function alreadyPaid(EnglishEnrollment $enrollment, int $amount, ?string $paidOn = null): EnglishPayment
    {
        return EnglishPayment::query()->create([
            'english_enrollment_id' => $enrollment->getKey(),
            'amount' => $amount,
            'paid_on' => $paidOn ?? now()->toDateString(),
        ]);
    }
}

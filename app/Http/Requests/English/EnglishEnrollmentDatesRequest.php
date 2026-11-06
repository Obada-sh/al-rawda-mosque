<?php

namespace App\Http\Requests\English;

use App\Http\Requests\Enrollments\EnrollmentDatesRequest;

/**
 * Correcting the enrollment date in an English course, or recording /
 * clearing the date the student left it: the same rules as the mosque's.
 */
class EnglishEnrollmentDatesRequest extends EnrollmentDatesRequest {}

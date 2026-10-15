<?php

namespace Tests\Concerns;

use App\Models\Circle;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;

/**
 * One course with two circles and three students, on a fixed week. The
 * tests of the reports all read this same week (the clock of the test
 * must stand after 6 October 2026):
 *
 *   sessions held: 1, 3 and 5 October 2026
 *   أحمد (حلقة أ): attended all three; recited 1-10 then 5-12 (12 new pages, 18 in all)
 *   بلال (حلقة أ): attended once; recited 1-4; lost 3 points
 *   خالد (حلقة ب): attended twice; recited nothing; won 7 points
 *
 * A page is worth 2 points and an attendance 1 point, so:
 *   أحمد 27 points, خالد 9, بلال 6.
 *
 * Requires BuildsDomain.
 */
trait SeedsReportWeek
{
    protected Course $course;

    protected Circle $circleA;

    protected Circle $circleB;

    protected Enrollment $ahmad;

    protected Enrollment $bilal;

    protected Enrollment $khalid;

    protected Teacher $yaser;

    protected Teacher $omar;

    protected function seedReportWeek(): void
    {
        $this->course = Course::factory()->create(['name' => 'دورة الخريف', 'points_per_page' => 2, 'points_per_attendance' => 1]);
        $this->circleA = Circle::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'حلقة أ']);
        $this->circleB = Circle::factory()->create(['course_id' => $this->course->getKey(), 'name' => 'حلقة ب']);

        $this->ahmad = $this->enroll(Student::factory()->create(['full_name' => 'أحمد']), $this->course, $this->circleA);
        $this->bilal = $this->enroll(Student::factory()->create(['full_name' => 'بلال']), $this->course, $this->circleA);
        $this->khalid = $this->enroll(Student::factory()->create(['full_name' => 'خالد']), $this->course, $this->circleB);

        $this->yaser = Teacher::factory()->create(['full_name' => 'الشيخ ياسر']);
        $this->omar = Teacher::factory()->create(['full_name' => 'الشيخ عمر']);

        foreach (['2026-10-01', '2026-10-03', '2026-10-05'] as $day) {
            $this->attend($this->ahmad, $day);
        }

        $this->attend($this->bilal, '2026-10-01');
        $this->attend($this->khalid, '2026-10-03');
        $this->attend($this->khalid, '2026-10-05');

        $this->recite($this->ahmad, 1, 10, '2026-10-01', $this->yaser);
        $this->recite($this->ahmad, 5, 12, '2026-10-03', $this->yaser);
        $this->recite($this->bilal, 1, 4, '2026-10-05', $this->omar);

        $this->award($this->khalid, 7, '2026-10-05');
        $this->award($this->bilal, -3, '2026-10-06');
    }
}

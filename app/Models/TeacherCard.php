<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's attendance card in one course. Its code is scanned at the
 * same desk as the students' cards and counts in that course only.
 *
 * @property int $id
 * @property int $course_id
 * @property int $teacher_id
 * @property string $card_code
 */
class TeacherCard extends Model
{
    /** Cards are given by Services\Teachers\TeacherCards only. */
    protected $fillable = [
        'course_id',
        'teacher_id',
        'card_code',
    ];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}

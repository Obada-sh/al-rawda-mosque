<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher came to a session of a course in which they supervise or
 * assist a circle.
 *
 * @property int $id
 * @property int $course_session_id
 * @property int $teacher_id
 * @property CarbonInterface|null $scanned_at
 * @property AttendanceMethod $method
 */
class TeacherAttendance extends Model
{
    protected $fillable = [
        'course_session_id',
        'teacher_id',
        'scanned_at',
        'method',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'method' => AttendanceMethod::class,
        ];
    }

    /** @return BelongsTo<CourseSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}

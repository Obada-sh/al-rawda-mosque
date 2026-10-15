<?php

namespace App\Models;

use App\Enums\AttendanceMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $course_session_id
 * @property int $enrollment_id
 * @property CarbonInterface|null $scanned_at
 * @property AttendanceMethod $method
 */
class Attendance extends Model
{
    protected $fillable = [
        'course_session_id',
        'enrollment_id',
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

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return HasOne<PointTransaction, $this> */
    public function pointTransaction(): HasOne
    {
        return $this->hasOne(PointTransaction::class);
    }
}

<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\CourseSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property CarbonInterface $session_date
 * @property SessionStatus $status
 */
class CourseSession extends Model
{
    /** @use HasFactory<CourseSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'session_date',
        'status',
    ];

    protected $attributes = [
        'status' => SessionStatus::Held->value,
    ];

    protected function casts(): array
    {
        return [
            'session_date' => Dates::CAST,
            'status' => SessionStatus::class,
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<TeacherAttendance, $this> */
    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    /** @param Builder<CourseSession> $query */
    public function scopeHeld(Builder $query): void
    {
        $query->where('status', SessionStatus::Held->value);
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled;
    }
}

<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property CarbonInterface $start_date
 * @property CarbonInterface|null $end_date
 * @property int $points_per_page
 * @property int $points_per_attendance
 * @property CourseStatus $status
 */
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'points_per_page',
        'points_per_attendance',
        'status',
    ];

    protected $attributes = [
        'points_per_page' => 0,
        'points_per_attendance' => 0,
        'status' => CourseStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => Dates::CAST,
            'end_date' => Dates::CAST,
            'points_per_page' => 'integer',
            'points_per_attendance' => 'integer',
            'status' => CourseStatus::class,
        ];
    }

    /** @return HasMany<CourseSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class);
    }

    /** @return HasMany<CourseSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /** @return HasMany<Circle, $this> */
    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Market, $this> the points markets of the course */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class);
    }

    /** @return HasMany<TeacherCard, $this> the attendance cards of the course's teachers */
    public function teacherCards(): HasMany
    {
        return $this->hasMany(TeacherCard::class);
    }

    /**
     * The transfers log of the course (students moved between its circles).
     *
     * @return HasManyThrough<CircleTransfer, Enrollment, $this>
     */
    public function transfers(): HasManyThrough
    {
        return $this->hasManyThrough(CircleTransfer::class, Enrollment::class);
    }

    /** @param Builder<Course> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CourseStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === CourseStatus::Active;
    }

    /**
     * Whether the date falls inside the course's start and end dates.
     */
    public function covers(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        if ($day < $this->start_date->toDateString()) {
            return false;
        }

        return $this->end_date === null || $day <= $this->end_date->toDateString();
    }

    /**
     * Whether the weekly timetable has a class on the weekday of the date.
     */
    public function isScheduledOn(CarbonInterface $date): bool
    {
        $weekday = Weekday::fromDate($date);

        return $this->schedules->contains(
            fn (CourseSchedule $schedule): bool => $schedule->weekday === $weekday,
        );
    }
}

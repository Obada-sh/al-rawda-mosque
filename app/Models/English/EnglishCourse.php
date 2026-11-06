<?php

namespace App\Models\English;

use App\Enums\CourseStatus;
use App\Enums\Weekday;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\English\EnglishCourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An English course: of one level, at a price, with its weekly class days,
 * its halls and its students.
 *
 * @property int $id
 * @property string $name
 * @property int $english_level_id
 * @property int $price Syrian pounds, for the whole course
 * @property CarbonInterface $start_date
 * @property CarbonInterface|null $end_date
 * @property CourseStatus $status
 */
class EnglishCourse extends Model
{
    /** @use HasFactory<EnglishCourseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'english_level_id',
        'price',
        'start_date',
        'end_date',
        'status',
    ];

    protected $attributes = [
        'price' => 0,
        'end_date' => null,
        'status' => CourseStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'start_date' => Dates::CAST,
            'end_date' => Dates::CAST,
            'status' => CourseStatus::class,
        ];
    }

    /** @return BelongsTo<EnglishLevel, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class, 'english_level_id');
    }

    /** @return HasMany<EnglishCourseSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(EnglishCourseSchedule::class);
    }

    /** @return HasMany<EnglishHall, $this> */
    public function halls(): HasMany
    {
        return $this->hasMany(EnglishHall::class);
    }

    /** @return HasMany<EnglishEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(EnglishEnrollment::class);
    }

    /** @return HasMany<EnglishSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(EnglishSession::class);
    }

    /** @return HasMany<EnglishTest, $this> */
    public function tests(): HasMany
    {
        return $this->hasMany(EnglishTest::class);
    }

    /** @return HasMany<EnglishPaper, $this> */
    public function papers(): HasMany
    {
        return $this->hasMany(EnglishPaper::class);
    }

    /** @param Builder<EnglishCourse> $query */
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
            fn (EnglishCourseSchedule $schedule): bool => $schedule->weekday === $weekday,
        );
    }
}

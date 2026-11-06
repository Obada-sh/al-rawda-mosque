<?php

namespace App\Models\English;

use App\Models\Student;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\English\EnglishEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student in an English course, in one of its halls. Owner of the
 * student's attendance, payments and test results in the course.
 *
 * @property int $id
 * @property int $student_id
 * @property int $english_course_id
 * @property int $english_hall_id
 * @property string $card_code
 * @property CarbonInterface $enrolled_on
 * @property CarbonInterface|null $left_on
 * @property int|null $price Syrian pounds; null = the course's price
 * @property string|null $price_note why the student pays another price
 */
class EnglishEnrollment extends Model
{
    /** @use HasFactory<EnglishEnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'english_course_id',
        'english_hall_id',
        'card_code',
        'enrolled_on',
        'left_on',
        'price',
        'price_note',
    ];

    protected $attributes = [
        'left_on' => null,
        'price' => null,
        'price_note' => null,
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => Dates::CAST,
            'left_on' => Dates::CAST,
            'price' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }

    /** @return BelongsTo<EnglishHall, $this> the student's current hall */
    public function hall(): BelongsTo
    {
        return $this->belongsTo(EnglishHall::class, 'english_hall_id');
    }

    /** @return HasMany<EnglishAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(EnglishAttendance::class);
    }

    /** @return HasMany<EnglishPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(EnglishPayment::class);
    }

    /** @return HasMany<EnglishTestResult, $this> */
    public function testResults(): HasMany
    {
        return $this->hasMany(EnglishTestResult::class);
    }

    /** @param Builder<EnglishEnrollment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('left_on');
    }

    public function hasLeft(): bool
    {
        return $this->left_on !== null;
    }

    public function hasOwnPrice(): bool
    {
        return $this->price !== null;
    }

    /**
     * What the student has to pay for the course, in Syrian pounds: the
     * student's own price, or the course's.
     */
    public function duePrice(): int
    {
        return $this->price ?? $this->course->price;
    }
}

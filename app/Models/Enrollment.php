<?php

namespace App\Models;

use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student in a course. Owner of every attendance, recitation and point.
 *
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int $circle_id
 * @property string $card_code
 * @property CarbonInterface $enrolled_on
 * @property CarbonInterface|null $left_on
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'circle_id',
        'card_code',
        'enrolled_on',
        'left_on',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_on' => Dates::CAST,
            'left_on' => Dates::CAST,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The student's current circle.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /** @return HasMany<CircleTransfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(CircleTransfer::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<Recitation, $this> */
    public function recitations(): HasMany
    {
        return $this->hasMany(Recitation::class);
    }

    /** @return HasMany<PointTransaction, $this> */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /** @return HasMany<MarketSale, $this> what the student bought at the course's points markets */
    public function marketSales(): HasMany
    {
        return $this->hasMany(MarketSale::class);
    }

    /** @return HasMany<JuzExam, $this> */
    public function juzExams(): HasMany
    {
        return $this->hasMany(JuzExam::class);
    }

    /**
     * The activities the student attended as a student of this course.
     *
     * @return HasMany<ActivityAttendance, $this>
     */
    public function activityAttendances(): HasMany
    {
        return $this->hasMany(ActivityAttendance::class);
    }

    /** @param Builder<Enrollment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('left_on');
    }

    public function hasLeft(): bool
    {
        return $this->left_on !== null;
    }
}

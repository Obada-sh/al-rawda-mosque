<?php

namespace App\Models;

use App\Support\Dates;
use App\Support\QuranPages;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $father_name
 * @property CarbonInterface|null $birth_date
 * @property string|null $phone
 * @property string|null $guardian_phone
 * @property string|null $address
 * @property int|null $tajweed_level_id
 * @property int|null $prior_memorized_pages
 * @property int $recited_pages_count
 * @property string|null $notes
 * @property string|null $photo_path
 * @property bool $is_profile_complete
 * @property CarbonInterface|null $archived_at
 * @property-read int|null $age
 * @property-read int $memorized_pages_total
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * recited_pages_count is deliberately not fillable: only the
     * memorization ledger may change it. Neither is photo_path: the file
     * and its name are written together, by SaveStudent.
     */
    protected $fillable = [
        'full_name',
        'father_name',
        'birth_date',
        'phone',
        'guardian_phone',
        'address',
        'tajweed_level_id',
        'prior_memorized_pages',
        'notes',
        'is_profile_complete',
        'archived_at',
    ];

    protected $attributes = [
        'father_name' => null,
        'recited_pages_count' => 0,
        'is_profile_complete' => false,
        'photo_path' => null,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => Dates::CAST,
            'prior_memorized_pages' => 'integer',
            'recited_pages_count' => 'integer',
            'is_profile_complete' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TajweedLevel, $this> */
    public function tajweedLevel(): BelongsTo
    {
        return $this->belongsTo(TajweedLevel::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<StudentPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(StudentPage::class);
    }

    /** @return HasMany<JuzExam, $this> */
    public function juzExams(): HasMany
    {
        return $this->hasMany(JuzExam::class);
    }

    /** @return HasManyThrough<Recitation, Enrollment, $this> */
    public function recitations(): HasManyThrough
    {
        return $this->hasManyThrough(Recitation::class, Enrollment::class);
    }

    /** @return HasManyThrough<PointTransaction, Enrollment, $this> */
    public function pointTransactions(): HasManyThrough
    {
        return $this->hasManyThrough(PointTransaction::class, Enrollment::class);
    }

    /**
     * What the student bought at the points markets, in every course.
     *
     * @return HasManyThrough<MarketSale, Enrollment, $this>
     */
    public function marketSales(): HasManyThrough
    {
        return $this->hasManyThrough(MarketSale::class, Enrollment::class);
    }

    /**
     * The activities the student attended, in every course.
     *
     * @return HasManyThrough<ActivityAttendance, Enrollment, $this>
     */
    public function activityAttendances(): HasManyThrough
    {
        return $this->hasManyThrough(ActivityAttendance::class, Enrollment::class);
    }

    /**
     * The student's moves between circles, in every course.
     *
     * @return HasManyThrough<CircleTransfer, Enrollment, $this>
     */
    public function transfers(): HasManyThrough
    {
        return $this->hasManyThrough(CircleTransfer::class, Enrollment::class);
    }

    /** @param Builder<Student> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param Builder<Student> $query */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /** @param Builder<Student> $query */
    public function scopeIncompleteProfile(Builder $query): void
    {
        $query->where('is_profile_complete', false);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Age in whole years, computed from the birth date so it never goes stale.
     *
     * @return Attribute<int|null, never>
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birth_date?->age);
    }

    /**
     * Everything the student has memorized: what they came with plus what
     * they recited in the system, capped at the size of the mushaf.
     *
     * @return Attribute<int, never>
     */
    protected function memorizedPagesTotal(): Attribute
    {
        return Attribute::get(fn (): int => min(
            QuranPages::LAST,
            (int) ($this->prior_memorized_pages ?? 0) + (int) $this->recited_pages_count,
        ));
    }
}

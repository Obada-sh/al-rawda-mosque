<?php

namespace App\Models;

use App\Enums\Section;
use App\Models\English\EnglishEnrollment;
use App\Models\English\EnglishLevel;
use App\Models\English\EnglishPayment;
use App\Models\English\EnglishTestResult;
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
 * A person who studies at the mosque, in the English section, or in both.
 * The flags in_mosque and in_english say which lists the student is on;
 * each section has its own level and its own "profile complete" flag.
 * Archiving is the mosque's: it does not hide a student from the English
 * section.
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $father_name
 * @property string|null $father_work
 * @property CarbonInterface|null $birth_date
 * @property string|null $phone
 * @property string|null $guardian_phone
 * @property string|null $address
 * @property int|null $tajweed_level_id
 * @property int|null $english_level_id
 * @property int|null $prior_memorized_pages
 * @property int $recited_pages_count
 * @property string|null $notes
 * @property string|null $photo_path
 * @property bool $is_profile_complete the mosque's profile
 * @property bool $english_profile_complete the English section's profile
 * @property bool $in_mosque
 * @property bool $in_english
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
     * and its name are written together, by SaveStudent. Nor are the
     * section flags and the completeness flags: SaveStudent and the
     * enrollment actions set them.
     */
    protected $fillable = [
        'full_name',
        'father_name',
        'father_work',
        'birth_date',
        'phone',
        'guardian_phone',
        'address',
        'tajweed_level_id',
        'english_level_id',
        'prior_memorized_pages',
        'notes',
        'is_profile_complete',
        'archived_at',
    ];

    protected $attributes = [
        'father_name' => null,
        'father_work' => null,
        'recited_pages_count' => 0,
        'is_profile_complete' => false,
        'english_profile_complete' => false,
        'english_level_id' => null,
        'in_mosque' => true,
        'in_english' => false,
        'photo_path' => null,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => Dates::CAST,
            'prior_memorized_pages' => 'integer',
            'recited_pages_count' => 'integer',
            'is_profile_complete' => 'boolean',
            'english_profile_complete' => 'boolean',
            'in_mosque' => 'boolean',
            'in_english' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TajweedLevel, $this> */
    public function tajweedLevel(): BelongsTo
    {
        return $this->belongsTo(TajweedLevel::class);
    }

    /** @return BelongsTo<EnglishLevel, $this> the student's own English level */
    public function englishLevel(): BelongsTo
    {
        return $this->belongsTo(EnglishLevel::class);
    }

    /** @return HasMany<EnglishEnrollment, $this> the student's English courses */
    public function englishEnrollments(): HasMany
    {
        return $this->hasMany(EnglishEnrollment::class);
    }

    /** @return HasManyThrough<EnglishPayment, EnglishEnrollment, $this> what the student paid for English courses */
    public function englishPayments(): HasManyThrough
    {
        return $this->hasManyThrough(EnglishPayment::class, EnglishEnrollment::class);
    }

    /** @return HasManyThrough<EnglishTestResult, EnglishEnrollment, $this> the student's marks in English courses */
    public function englishTestResults(): HasManyThrough
    {
        return $this->hasManyThrough(EnglishTestResult::class, EnglishEnrollment::class);
    }

    /** @return HasMany<Enrollment, $this> the student's mosque courses */
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

    /**
     * Students whose profile in the section is not complete.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeIncompleteProfile(Builder $query, Section $section = Section::Mosque): void
    {
        $query->where($section->profileFlag(), false);
    }

    /** @param Builder<Student> $query */
    public function scopeInMosque(Builder $query): void
    {
        $query->where('in_mosque', true);
    }

    /** @param Builder<Student> $query */
    public function scopeInEnglish(Builder $query): void
    {
        $query->where('in_english', true);
    }

    /** @param Builder<Student> $query */
    public function scopeInSection(Builder $query, Section $section): void
    {
        $query->where($section->studentFlag(), true);
    }

    public function isIn(Section $section): bool
    {
        return (bool) $this->getAttribute($section->studentFlag());
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

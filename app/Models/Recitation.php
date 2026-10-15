<?php

namespace App\Models;

use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $teacher_id
 * @property CarbonInterface $recited_on
 * @property int $from_page
 * @property int $to_page
 * @property int $pages_count
 * @property int $new_pages_count
 * @property int $points_per_page
 * @property string|null $notes
 */
class Recitation extends Model
{
    /**
     * new_pages_count is deliberately not fillable: only the memorization
     * ledger may change it.
     */
    protected $fillable = [
        'enrollment_id',
        'teacher_id',
        'recited_on',
        'from_page',
        'to_page',
        'pages_count',
        'points_per_page',
        'notes',
    ];

    protected $attributes = [
        'new_pages_count' => 0,
        'points_per_page' => 0,
    ];

    protected function casts(): array
    {
        return [
            'recited_on' => Dates::CAST,
            'from_page' => 'integer',
            'to_page' => 'integer',
            'pages_count' => 'integer',
            'new_pages_count' => 'integer',
            'points_per_page' => 'integer',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return HasOne<PointTransaction, $this> */
    public function pointTransaction(): HasOne
    {
        return $this->hasOne(PointTransaction::class);
    }

    /**
     * The pages this recitation owns (the ones it was first to cover).
     *
     * @return HasMany<StudentPage, $this>
     */
    public function ownedPages(): HasMany
    {
        return $this->hasMany(StudentPage::class);
    }

    /**
     * Pages that were already memorized when this recitation was heard.
     */
    public function repeatedPagesCount(): int
    {
        return $this->pages_count - $this->new_pages_count;
    }
}

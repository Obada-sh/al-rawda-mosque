<?php

namespace App\Models\English;

use App\Enums\SessionStatus;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\English\EnglishSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class day of an English course.
 *
 * @property int $id
 * @property int $english_course_id
 * @property CarbonInterface $session_date
 * @property SessionStatus $status
 */
class EnglishSession extends Model
{
    /** @use HasFactory<EnglishSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'english_course_id',
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

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }

    /** @return HasMany<EnglishAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(EnglishAttendance::class);
    }

    /** @param Builder<EnglishSession> $query */
    public function scopeHeld(Builder $query): void
    {
        $query->where('status', SessionStatus::Held->value);
    }

    public function isCancelled(): bool
    {
        return $this->status === SessionStatus::Cancelled;
    }
}

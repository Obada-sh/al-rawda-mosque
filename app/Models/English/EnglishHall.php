<?php

namespace App\Models\English;

use Database\Factories\English\EnglishHallFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hall (قاعة) of an English course, with its teacher and assistant.
 *
 * @property int $id
 * @property int $english_course_id
 * @property string $name
 * @property int|null $teacher_id
 * @property int|null $assistant_id
 * @property bool $is_active
 */
class EnglishHall extends Model
{
    /** @use HasFactory<EnglishHallFactory> */
    use HasFactory;

    protected $fillable = [
        'english_course_id',
        'name',
        'teacher_id',
        'assistant_id',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'teacher_id' => null,
        'assistant_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }

    /** @return BelongsTo<EnglishTeacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(EnglishTeacher::class, 'teacher_id');
    }

    /** @return BelongsTo<EnglishTeacher, $this> */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(EnglishTeacher::class, 'assistant_id');
    }

    /** @return HasMany<EnglishEnrollment, $this> the students in the hall now */
    public function enrollments(): HasMany
    {
        return $this->hasMany(EnglishEnrollment::class);
    }

    /** @param Builder<EnglishHall> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

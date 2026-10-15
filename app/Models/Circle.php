<?php

namespace App\Models;

use Database\Factories\CircleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property int|null $supervisor_teacher_id
 * @property int|null $assistant_teacher_id
 * @property bool $is_active
 * @property string|null $logo_path
 */
class Circle extends Model
{
    /** @use HasFactory<CircleFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'name',
        'supervisor_teacher_id',
        'assistant_teacher_id',
        'is_active',
    ];

    /** The logo is set by SaveCircle only. */
    protected $attributes = [
        'is_active' => true,
        'logo_path' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'supervisor_teacher_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assistant_teacher_id');
    }

    /**
     * The students currently in the circle.
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<CircleTransfer, $this> */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(CircleTransfer::class, 'to_circle_id');
    }

    /** @return HasMany<CircleTransfer, $this> */
    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(CircleTransfer::class, 'from_circle_id');
    }

    /**
     * The activities held for this circle.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @param Builder<Circle> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

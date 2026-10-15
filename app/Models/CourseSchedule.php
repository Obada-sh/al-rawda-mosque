<?php

namespace App\Models;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property Weekday $weekday
 * @property string $start_time
 * @property string $end_time
 */
class CourseSchedule extends Model
{
    protected $fillable = [
        'course_id',
        'weekday',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

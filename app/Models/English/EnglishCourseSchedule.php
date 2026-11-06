<?php

namespace App\Models\English;

use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weekly class day of an English course.
 *
 * @property int $id
 * @property int $english_course_id
 * @property Weekday $weekday
 * @property string $start_time
 * @property string $end_time
 */
class EnglishCourseSchedule extends Model
{
    protected $fillable = [
        'english_course_id',
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

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }
}

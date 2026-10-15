<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A student who attended an activity, as the student of a course (an
 * enrollment): the points it earns belong to that course.
 *
 * @property int $id
 * @property int $activity_id
 * @property int $enrollment_id
 */
class ActivityAttendance extends Model
{
    protected $fillable = [
        'activity_id',
        'enrollment_id',
    ];

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * The points this attendance earned; none when the activity gives no points.
     *
     * @return HasOne<PointTransaction, $this>
     */
    public function pointTransaction(): HasOne
    {
        return $this->hasOne(PointTransaction::class);
    }
}

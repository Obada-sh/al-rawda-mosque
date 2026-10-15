<?php

namespace App\Models;

use App\Enums\PointSource;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the points ledger.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property PointSource $source
 * @property int|null $attendance_id
 * @property int|null $recitation_id
 * @property int|null $activity_attendance_id
 * @property int $points
 * @property string|null $reason
 * @property CarbonInterface $occurred_on
 */
class PointTransaction extends Model
{
    protected $fillable = [
        'enrollment_id',
        'source',
        'attendance_id',
        'recitation_id',
        'activity_attendance_id',
        'points',
        'reason',
        'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'source' => PointSource::class,
            'points' => 'integer',
            'occurred_on' => Dates::CAST,
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<Recitation, $this> */
    public function recitation(): BelongsTo
    {
        return $this->belongsTo(Recitation::class);
    }

    /** @return BelongsTo<ActivityAttendance, $this> */
    public function activityAttendance(): BelongsTo
    {
        return $this->belongsTo(ActivityAttendance::class);
    }

    public function isManual(): bool
    {
        return $this->source === PointSource::Manual;
    }
}

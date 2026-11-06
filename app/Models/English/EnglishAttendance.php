<?php

namespace App\Models\English;

use App\Enums\AttendanceMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student present at a class day of an English course.
 *
 * @property int $id
 * @property int $english_session_id
 * @property int $english_enrollment_id
 * @property CarbonInterface|null $scanned_at
 * @property AttendanceMethod $method
 */
class EnglishAttendance extends Model
{
    protected $fillable = [
        'english_session_id',
        'english_enrollment_id',
        'scanned_at',
        'method',
    ];

    protected $attributes = [
        'scanned_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'method' => AttendanceMethod::class,
        ];
    }

    /** @return BelongsTo<EnglishSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EnglishSession::class, 'english_session_id');
    }

    /** @return BelongsTo<EnglishEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EnglishEnrollment::class, 'english_enrollment_id');
    }
}

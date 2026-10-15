<?php

namespace App\Models;

use App\Enums\ActivityAudience;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An activity outside the lessons (a trip, a competition, a meeting), for
 * one circle or for the whole mosque. Each student who attended gains
 * the points of the activity.
 *
 * @property int $id
 * @property string $title
 * @property CarbonInterface $activity_date
 * @property int|null $circle_id
 * @property int $points
 * @property string|null $notes
 */
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'activity_date',
        'circle_id',
        'points',
        'notes',
    ];

    protected $attributes = [
        'points' => 0,
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => Dates::CAST,
            'points' => 'integer',
        ];
    }

    /**
     * The circle the activity is for; none for an activity of the whole mosque.
     *
     * @return BelongsTo<Circle, $this>
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    /**
     * Who attended.
     *
     * @return HasMany<ActivityAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(ActivityAttendance::class);
    }

    public function audience(): ActivityAudience
    {
        return $this->circle_id === null ? ActivityAudience::Mosque : ActivityAudience::Circle;
    }
}

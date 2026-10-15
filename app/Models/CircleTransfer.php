<?php

namespace App\Models;

use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the transfers log. Display only.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $from_circle_id
 * @property int $to_circle_id
 * @property CarbonInterface $transferred_on
 * @property string|null $notes
 */
class CircleTransfer extends Model
{
    protected $fillable = [
        'enrollment_id',
        'from_circle_id',
        'to_circle_id',
        'transferred_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transferred_on' => Dates::CAST,
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Circle, $this> */
    public function fromCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'from_circle_id');
    }

    /** @return BelongsTo<Circle, $this> */
    public function toCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'to_circle_id');
    }
}

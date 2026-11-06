<?php

namespace App\Models\English;

use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a student paid for an English course, in Syrian pounds.
 *
 * @property int $id
 * @property int $english_enrollment_id
 * @property int $amount
 * @property CarbonInterface $paid_on
 * @property string|null $notes
 */
class EnglishPayment extends Model
{
    protected $fillable = [
        'english_enrollment_id',
        'amount',
        'paid_on',
        'notes',
    ];

    protected $attributes = [
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => Dates::CAST,
        ];
    }

    /** @return BelongsTo<EnglishEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EnglishEnrollment::class, 'english_enrollment_id');
    }
}

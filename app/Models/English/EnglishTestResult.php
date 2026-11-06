<?php

namespace App\Models\English;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's result in a test: a mark out of the test's full mark, or
 * absent.
 *
 * @property int $id
 * @property int $english_test_id
 * @property int $english_enrollment_id
 * @property string|null $mark null when absent
 * @property bool $is_absent
 * @property string|null $notes
 */
class EnglishTestResult extends Model
{
    protected $fillable = [
        'english_test_id',
        'english_enrollment_id',
        'mark',
        'is_absent',
        'notes',
    ];

    protected $attributes = [
        'mark' => null,
        'is_absent' => false,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    /** @return BelongsTo<EnglishTest, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(EnglishTest::class, 'english_test_id');
    }

    /** @return BelongsTo<EnglishEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(EnglishEnrollment::class, 'english_enrollment_id');
    }
}

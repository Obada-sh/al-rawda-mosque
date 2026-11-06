<?php

namespace App\Models\English;

use App\Enums\English\TestKind;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An exam or a recitation of an English course, held on paper or aloud:
 * the system keeps the date, the full mark and each student's result.
 *
 * @property int $id
 * @property int $english_course_id
 * @property string $title
 * @property TestKind $kind
 * @property CarbonInterface $held_on
 * @property int $max_mark
 * @property string|null $notes
 */
class EnglishTest extends Model
{
    protected $fillable = [
        'english_course_id',
        'title',
        'kind',
        'held_on',
        'max_mark',
        'notes',
    ];

    protected $attributes = [
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'kind' => TestKind::class,
            'held_on' => Dates::CAST,
            'max_mark' => 'integer',
        ];
    }

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }

    /** @return HasMany<EnglishTestResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(EnglishTestResult::class);
    }
}

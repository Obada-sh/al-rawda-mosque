<?php

namespace App\Models\English;

use App\Models\Student;
use Database\Factories\English\EnglishLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A level of the English section (A1, A2, ...): of a course, and of a
 * student. The cards of a course's students are drawn on the background
 * picture of its level.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property string|null $card_background_path
 */
class EnglishLevel extends Model
{
    /** @use HasFactory<EnglishLevelFactory> */
    use HasFactory;

    /** The background is set by SaveEnglishLevel only. */
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'card_background_path' => null,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<EnglishCourse, $this> */
    public function courses(): HasMany
    {
        return $this->hasMany(EnglishCourse::class);
    }

    /** @return HasMany<Student, $this> the students whose own English level this is */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}

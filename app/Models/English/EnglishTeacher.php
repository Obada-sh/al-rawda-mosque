<?php

namespace App\Models\English;

use Database\Factories\English\EnglishTeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teacher of the English section: a list of its own, apart from the
 * mosque's teachers.
 *
 * @property int $id
 * @property string $full_name
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $notes
 */
class EnglishTeacher extends Model
{
    /** @use HasFactory<EnglishTeacherFactory> */
    use HasFactory;

    protected $fillable = [
        'full_name',
        'phone',
        'is_active',
        'notes',
    ];

    protected $attributes = [
        'is_active' => true,
        'phone' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<EnglishHall, $this> the halls the teacher teaches */
    public function halls(): HasMany
    {
        return $this->hasMany(EnglishHall::class, 'teacher_id');
    }

    /** @return HasMany<EnglishHall, $this> the halls the teacher assists in */
    public function assistedHalls(): HasMany
    {
        return $this->hasMany(EnglishHall::class, 'assistant_id');
    }

    /** @param Builder<EnglishTeacher> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

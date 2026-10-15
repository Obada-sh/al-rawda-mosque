<?php

namespace App\Models;

use Database\Factories\TajweedLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class TajweedLevel extends Model
{
    /** @use HasFactory<TajweedLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}

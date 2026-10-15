<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $photo_path
 */
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /** The photo is set by SaveTeacher only. */
    protected $fillable = [
        'full_name',
        'phone',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'photo_path' => null,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Circle, $this> */
    public function supervisedCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'supervisor_teacher_id');
    }

    /** @return HasMany<Circle, $this> */
    public function assistedCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'assistant_teacher_id');
    }

    /** @return HasMany<Recitation, $this> */
    public function recitations(): HasMany
    {
        return $this->hasMany(Recitation::class);
    }

    /** @return HasMany<JuzExam, $this> */
    public function examinedExams(): HasMany
    {
        return $this->hasMany(JuzExam::class, 'examiner_teacher_id');
    }

    /** @return HasMany<TeacherCard, $this> the teacher's attendance card in each course */
    public function cards(): HasMany
    {
        return $this->hasMany(TeacherCard::class);
    }

    /** @return HasMany<TeacherAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class);
    }

    /** @param Builder<Teacher> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

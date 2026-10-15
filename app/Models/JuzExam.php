<?php

namespace App\Models;

use App\Enums\ExamGrade;
use App\Enums\ExamOrigin;
use App\Enums\ExamStatus;
use App\Support\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $juz_no
 * @property int|null $enrollment_id
 * @property ExamStatus $status
 * @property ExamOrigin $origin
 * @property CarbonInterface $triggered_on
 * @property CarbonInterface|null $exam_date
 * @property int|null $examiner_teacher_id
 * @property ExamGrade|null $grade
 * @property string|null $notes
 */
class JuzExam extends Model
{
    protected $fillable = [
        'student_id',
        'juz_no',
        'enrollment_id',
        'status',
        'origin',
        'triggered_on',
        'exam_date',
        'examiner_teacher_id',
        'grade',
        'notes',
    ];

    protected $attributes = [
        'status' => ExamStatus::Pending->value,
        'origin' => ExamOrigin::Auto->value,
    ];

    protected function casts(): array
    {
        return [
            'juz_no' => 'integer',
            'status' => ExamStatus::class,
            'origin' => ExamOrigin::class,
            'triggered_on' => Dates::CAST,
            'exam_date' => Dates::CAST,
            'grade' => ExamGrade::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<QuranJuz, $this> */
    public function juz(): BelongsTo
    {
        return $this->belongsTo(QuranJuz::class, 'juz_no', 'juz_no');
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function examiner(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'examiner_teacher_id');
    }

    /** @param Builder<JuzExam> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ExamStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === ExamStatus::Pending;
    }
}

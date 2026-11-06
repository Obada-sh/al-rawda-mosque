<?php

namespace App\Models\English;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file of an English course (a sheet, a picture), kept on the private
 * disk under english-papers/.
 *
 * @property int $id
 * @property int $english_course_id
 * @property string $title
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size bytes
 * @property string|null $notes
 */
class EnglishPaper extends Model
{
    /** The file columns are set by SaveEnglishPaper only. */
    protected $fillable = [
        'english_course_id',
        'title',
        'notes',
    ];

    protected $attributes = [
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<EnglishCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(EnglishCourse::class, 'english_course_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}

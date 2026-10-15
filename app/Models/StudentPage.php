<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read model for a memorized page.
 *
 * The table has a composite key (student_id, page_no) and is written only
 * by the memorization ledger through the query builder, so this model is
 * used for reading and relations only.
 *
 * @property int $student_id
 * @property int $page_no
 * @property int $recitation_id
 */
class StudentPage extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'page_no' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Recitation, $this> */
    public function recitation(): BelongsTo
    {
        return $this->belongsTo(Recitation::class);
    }
}

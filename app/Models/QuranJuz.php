<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixed reference data: the page range of a juz.
 *
 * @property int $juz_no
 * @property int $from_page
 * @property int $to_page
 */
class QuranJuz extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'quran_juz';

    protected $primaryKey = 'juz_no';

    protected $keyType = 'int';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'juz_no' => 'integer',
            'from_page' => 'integer',
            'to_page' => 'integer',
        ];
    }

    public function pagesCount(): int
    {
        return $this->to_page - $this->from_page + 1;
    }
}

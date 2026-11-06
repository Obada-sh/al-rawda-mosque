<?php

namespace App\Http\Requests\English;

use App\Http\Requests\BaseRequest;

/**
 * Moving an enrolled student to another hall of the same course.
 */
class EnglishHallChangeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'english_hall_id' => ['required', 'integer', 'exists:english_halls,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'english_hall_id' => 'القاعة الجديدة',
        ];
    }
}

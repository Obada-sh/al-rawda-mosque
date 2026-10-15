<?php

namespace App\Actions\TajweedLevels;

use App\Http\Requests\Students\TajweedLevelRequest;
use App\Models\TajweedLevel;
use Illuminate\Validation\ValidationException;

final class SaveTajweedLevel
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?TajweedLevel $level = null): TajweedLevel
    {
        $data = TajweedLevelRequest::validateData($input, ['ignore_id' => $level?->getKey()]);

        $level ??= new TajweedLevel;
        $level->fill($data)->save();

        return $level;
    }
}

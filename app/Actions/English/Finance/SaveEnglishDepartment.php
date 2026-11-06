<?php

namespace App\Actions\English\Finance;

use App\Http\Requests\English\EnglishDepartmentRequest;
use App\Models\English\EnglishDepartment;
use Illuminate\Validation\ValidationException;

final class SaveEnglishDepartment
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?EnglishDepartment $department = null): EnglishDepartment
    {
        $data = EnglishDepartmentRequest::validateData($input, ['ignore_id' => $department?->getKey()]);

        $department ??= new EnglishDepartment;
        $department->fill($data)->save();

        return $department;
    }
}

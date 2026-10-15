<?php

namespace App\Actions\Finance;

use App\Http\Requests\Finance\DepartmentRequest;
use App\Models\Department;
use Illuminate\Validation\ValidationException;

final class SaveDepartment
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?Department $department = null): Department
    {
        $data = DepartmentRequest::validateData($input, ['ignore_id' => $department?->getKey()]);

        $department ??= new Department;
        $department->fill($data)->save();

        return $department;
    }
}

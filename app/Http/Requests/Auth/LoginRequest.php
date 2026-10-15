<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * What the sign-in form must contain before the credentials are checked.
 */
class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'اسم المستخدم',
            'password' => 'كلمة المرور',
        ];
    }
}
